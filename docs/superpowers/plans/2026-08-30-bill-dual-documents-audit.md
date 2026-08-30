# Bill Dual Documents + Post-Ledger Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bills attach a supplier invoice (OCR) and optional payment receipt; after ledger post, documents can be replaced with retained versions and a required reason; OCR UI shows phase-based progress so scans do not look stuck.

**Architecture:** Two current-path columns on `bills` plus append-only `bill_document_versions`. `BillDocumentService` owns store/replace/clear/versioning. Controllers expose upload + serve + OCR status with `phase`/`progress`. React uses a slot-aware upload component with upload→queue→scan progress mapping.

**Tech Stack:** Laravel 12 (tenant migrations), PHPUnit via `/opt/homebrew/bin/php artisan test`, Inertia React, axios, existing `ProcessOcr` / `OcrResultCache`.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-30-bill-dual-documents-audit-design.md`
- Homebrew PHP: `/opt/homebrew/bin/php artisan test --filter=…` (Herd `php` can fail on dump-loader).
- Do **not** git commit unless the user explicitly asks (user rule overrides “frequent commits” in this skill). Skip every Commit step unless told to commit.
- OCR only on `supplier_invoice`. Payment receipt never triggers `ProcessOcr`.
- Payment receipt always optional — never block post/pay.
- After non-draft: replace requires `reason`; clear forbidden; OCR must not overwrite posted amounts.
- Storage prefix stays `receipts/` (and legacy `copilot-receipts/`) for path sanitisation compatibility.
- After React changes: `npm run build`. Hard-refresh (`Cmd+Shift+R`).
- Do not rework sales `invoice_attachments` or full Receipt Inbox UI beyond path/label mapping.

## File map

- Create: `database/migrations/tenant/2026_08_30_200000_bill_dual_documents_and_versions.php`
- Create: `app/Models/BillDocumentVersion.php`
- Create: `app/Services/BillDocumentService.php`
- Create: `app/Support/OcrProgress.php`
- Create: `tests/Unit/Support/OcrProgressTest.php`
- Create: `tests/Feature/Bills/BillDocumentUploadTest.php`
- Create: `resources/js/Components/BillDocumentUpload.jsx`
- Modify: `app/Models/Bill.php`
- Modify: `app/Services/BillService.php`
- Modify: `app/Http/Controllers/BillController.php`
- Modify: `app/Http/Controllers/ReceiptInboxController.php`
- Modify: `app/Http/Requests/StoreBillRequest.php`
- Modify: `app/Http/Requests/UpdateBillRequest.php`
- Modify: `app/Jobs/ProcessOcr.php` (if it writes `receipt_path`)
- Modify: `app/Services/OCRService.php` (if it writes `receipt_path`)
- Modify: `app/Services/Copilot/CopilotTools.php`
- Modify: `app/Services/Copilot/CopilotCatalog.php` (optional alias)
- Modify: `routes/web.php`
- Modify: `resources/js/Components/ReceiptUpload.jsx` (thin wrapper → BillDocumentUpload)
- Modify: `resources/js/Pages/Bills/_Form.jsx`
- Modify: `resources/js/Pages/Bills/Create.jsx`
- Modify: `resources/js/Pages/Bills/Edit.jsx`
- Modify: `resources/js/Pages/Bills/Show.jsx`
- Modify: `resources/js/Pages/Bills/Index.jsx`
- Modify: `resources/js/Pages/Audit/Index.jsx`
- Modify: `tests/Feature/Ocr/ReceiptInboxTest.php`

---

### Task 1: Migration — dual paths + versions table

**Files:**
- Create: `database/migrations/tenant/2026_08_30_200000_bill_dual_documents_and_versions.php`

**Interfaces:**
- Produces columns on `bills`: `supplier_invoice_path`, `payment_receipt_path`
- Produces table `bill_document_versions` as in spec
- Migrates `receipt_path` → `supplier_invoice_path`, seeds version rows, drops `receipt_path`

- [ ] **Step 1: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->string('supplier_invoice_path')->nullable()->after('reference');
            $table->string('payment_receipt_path')->nullable()->after('supplier_invoice_path');
        });

        if (Schema::hasColumn('bills', 'receipt_path')) {
            DB::table('bills')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    if (! empty($row->receipt_path)) {
                        DB::table('bills')->where('id', $row->id)->update([
                            'supplier_invoice_path' => $row->receipt_path,
                        ]);
                    }
                }
            });
        }

        Schema::create('bill_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->string('slot', 32); // supplier_invoice | payment_receipt
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('action', 16); // uploaded | replaced | cleared
            $table->string('reason')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['bill_id', 'slot', 'created_at']);
        });

        if (Schema::hasColumn('bills', 'receipt_path')) {
            $now = now();
            DB::table('bills')->whereNotNull('supplier_invoice_path')->orderBy('id')->chunkById(100, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    DB::table('bill_document_versions')->insert([
                        'bill_id' => $row->id,
                        'slot' => 'supplier_invoice',
                        'path' => $row->supplier_invoice_path,
                        'original_filename' => null,
                        'mime' => null,
                        'size_bytes' => null,
                        'action' => 'uploaded',
                        'reason' => null,
                        'uploaded_by' => $row->created_by ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });

            Schema::table('bills', function (Blueprint $table) {
                $table->dropColumn('receipt_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            if (! Schema::hasColumn('bills', 'receipt_path')) {
                $table->string('receipt_path')->nullable()->after('reference');
            }
        });

        DB::table('bills')->whereNotNull('supplier_invoice_path')->update([
            'receipt_path' => DB::raw('supplier_invoice_path'),
        ]);

        Schema::dropIfExists('bill_document_versions');

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['supplier_invoice_path', 'payment_receipt_path']);
        });
    }
};
```

- [ ] **Step 2: Confirm tenant migration is picked up**

Run the project’s usual tenant migrate (e.g. `php artisan tenants:migrate`). Expected: applies cleanly on a local tenant DB.

- [ ] **Step 3: Skip commit** unless user asked

---

### Task 2: `OcrProgress` helper + unit tests

**Files:**
- Create: `app/Support/OcrProgress.php`
- Create: `tests/Unit/Support/OcrProgressTest.php`

**Interfaces:**
- Produces:
  - `OcrProgress::forUploadPercent(int $uploadPercent): array{phase,progress,label}`
  - `OcrProgress::forPending(int $elapsedMs): array{phase,progress,label}` — queued then processing, caps at 90
  - `OcrProgress::completed(): array`
  - `OcrProgress::failed(): array`

- [ ] **Step 1: Write failing unit tests**

```php
<?php

namespace Tests\Unit\Support;

use App\Support\OcrProgress;
use PHPUnit\Framework\TestCase;

class OcrProgressTest extends TestCase
{
    public function test_upload_maps_into_first_quarter(): void
    {
        $p = OcrProgress::forUploadPercent(100);
        $this->assertSame('upload', $p['phase']);
        $this->assertSame(25, $p['progress']);
    }

    public function test_pending_starts_queued_then_processing_and_caps_at_90(): void
    {
        $early = OcrProgress::forPending(500);
        $this->assertSame('queued', $early['phase']);
        $this->assertGreaterThanOrEqual(25, $early['progress']);
        $this->assertLessThanOrEqual(35, $early['progress']);

        $mid = OcrProgress::forPending(15_000);
        $this->assertSame('processing', $mid['phase']);
        $this->assertGreaterThan(35, $mid['progress']);
        $this->assertLessThanOrEqual(90, $mid['progress']);

        $late = OcrProgress::forPending(600_000);
        $this->assertSame(90, $late['progress']);
    }

    public function test_completed_and_failed(): void
    {
        $this->assertSame(100, OcrProgress::completed()['progress']);
        $this->assertSame('failed', OcrProgress::failed()['phase']);
    }
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
/opt/homebrew/bin/php artisan test --filter=OcrProgressTest
```

Expected: FAIL (class not found)

- [ ] **Step 3: Implement `OcrProgress`**

```php
<?php

namespace App\Support;

final class OcrProgress
{
    public static function forUploadPercent(int $uploadPercent): array
    {
        $uploadPercent = max(0, min(100, $uploadPercent));
        $progress = (int) round($uploadPercent * 0.25);

        return [
            'phase' => 'upload',
            'progress' => $progress,
            'label' => 'Uploading invoice…',
        ];
    }

    public static function forPending(int $elapsedMs): array
    {
        if ($elapsedMs < 2_000) {
            $t = max(0, min(1, $elapsedMs / 2_000));
            $progress = (int) round(25 + ($t * 10));

            return [
                'phase' => 'queued',
                'progress' => $progress,
                'label' => 'Waiting for scan…',
            ];
        }

        $t = max(0, min(1, ($elapsedMs - 2_000) / 60_000));
        $progress = (int) round(35 + ($t * 55));

        return [
            'phase' => 'processing',
            'progress' => min(90, $progress),
            'label' => 'Scanning invoice…',
        ];
    }

    public static function completed(): array
    {
        return [
            'phase' => 'done',
            'progress' => 100,
            'label' => 'Scan complete',
        ];
    }

    public static function failed(): array
    {
        return [
            'phase' => 'failed',
            'progress' => 100,
            'label' => 'Scan failed — enter details manually',
        ];
    }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
/opt/homebrew/bin/php artisan test --filter=OcrProgressTest
```

- [ ] **Step 5: Skip commit** unless user asked

---

### Task 3: Model + `BillDocumentService`

**Files:**
- Create: `app/Models/BillDocumentVersion.php`
- Create: `app/Services/BillDocumentService.php`
- Modify: `app/Models/Bill.php`

**Interfaces:**
- `Bill::documentVersions()` hasMany
- Fillable: `supplier_invoice_path`, `payment_receipt_path` (remove `receipt_path`)
- Accessors: `supplier_invoice_url`, `payment_receipt_url`
- `BillDocumentService::pathColumn(string $slot): string`
- `BillDocumentService::attach(Bill $bill, string $slot, UploadedFile $file, ?string $reason, ?int $userId): BillDocumentVersion`
- `BillDocumentService::storeFile(UploadedFile $file): string` (orphan create-flow)
- `BillDocumentService::clear(Bill $bill, string $slot, ?int $userId): void` — rejects non-draft

- [ ] **Step 1: Update `Bill` model**

- Replace `receipt_path` in `$fillable` with `supplier_invoice_path`, `payment_receipt_path`.
- Replace `$appends` `receipt_url` with `supplier_invoice_url`, `payment_receipt_url`.
- Point accessors at the new columns / routes `bills.document?slot=…`.
- Add `documentVersions()` relationship (`hasMany` → `latest('id')`).

Example accessors:

```php
public function getSupplierInvoiceUrlAttribute(): ?string
{
    $path = $this->getAttributes()['supplier_invoice_path'] ?? null;
    if (! $path) {
        return null;
    }

    return route('bills.document', $this->id).'?slot=supplier_invoice';
}

public function getPaymentReceiptUrlAttribute(): ?string
{
    $path = $this->getAttributes()['payment_receipt_path'] ?? null;
    if (! $path) {
        return null;
    }

    return route('bills.document', $this->id).'?slot=payment_receipt';
}
```

- [ ] **Step 2: Create `BillDocumentVersion` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillDocumentVersion extends Model
{
    protected $fillable = [
        'bill_id',
        'slot',
        'path',
        'original_filename',
        'mime',
        'size_bytes',
        'action',
        'reason',
        'uploaded_by',
    ];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
```

- [ ] **Step 3: Implement `BillDocumentService`**

Inject/reuse metadata stripper pattern from `BillController`. Core:

```php
public function pathColumn(string $slot): string
{
    return match ($slot) {
        'supplier_invoice' => 'supplier_invoice_path',
        'payment_receipt' => 'payment_receipt_path',
        default => throw new \InvalidArgumentException('Invalid slot'),
    };
}

public function attach(
    Bill $bill,
    string $slot,
    \Illuminate\Http\UploadedFile $file,
    ?string $reason,
    ?int $userId,
): BillDocumentVersion {
    $column = $this->pathColumn($slot);
    $isDraft = $bill->status === 'draft';

    if (! $isDraft && trim((string) $reason) === '') {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'reason' => 'A reason is required to replace documents after the bill is posted.',
        ]);
    }

    $path = $this->storeFile($file);
    $previous = $bill->{$column};
    $action = $previous ? 'replaced' : 'uploaded';

    $updates = [$column => $path];
    if ($slot === 'supplier_invoice' && $isDraft) {
        $updates['ocr_status'] = 'pending';
    }
    $bill->update($updates);

    return BillDocumentVersion::create([
        'bill_id' => $bill->id,
        'slot' => $slot,
        'path' => $path,
        'original_filename' => $file->getClientOriginalName(),
        'mime' => $file->getClientMimeType(),
        'size_bytes' => $file->getSize(),
        'action' => $action,
        'reason' => $isDraft ? $reason : trim((string) $reason),
        'uploaded_by' => $userId,
    ]);
}

public function clear(Bill $bill, string $slot, ?int $userId): void
{
    if ($bill->status !== 'draft') {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'slot' => 'Documents cannot be removed after the bill is posted. Replace the file instead.',
        ]);
    }

    $column = $this->pathColumn($slot);
    $previous = $bill->{$column};
    if (! $previous) {
        return;
    }

    $bill->update([$column => null]);

    BillDocumentVersion::create([
        'bill_id' => $bill->id,
        'slot' => $slot,
        'path' => $previous,
        'action' => 'cleared',
        'reason' => null,
        'uploaded_by' => $userId,
    ]);
}
```

`storeFile`: strip EXIF when applicable, `store('receipts', 'public')`, throw/return clearly on failure.

- [ ] **Step 4: Skip commit** unless user asked

---

### Task 4: Wire `BillService`, requests, inbox, Copilot, OCR writers

**Files:**
- Modify: `app/Services/BillService.php`
- Modify: `app/Http/Requests/StoreBillRequest.php`
- Modify: `app/Http/Requests/UpdateBillRequest.php`
- Modify: `app/Http/Controllers/ReceiptInboxController.php`
- Modify: `app/Services/OCRService.php` / `app/Jobs/ProcessOcr.php` as needed
- Modify: `app/Services/Copilot/CopilotTools.php`
- Modify: `app/Services/Copilot/CopilotCatalog.php` (optional)
- Modify: `tests/Feature/Ocr/ReceiptInboxTest.php`

**Interfaces:**
- Create/update payloads use `supplier_invoice_path` / `payment_receipt_path`
- Create seeds `uploaded` version rows when paths present
- Inbox confirm maps to `supplier_invoice_path`
- Copilot `receipt_path` arg persists as `supplier_invoice_path`

- [ ] **Step 1: Request validation**

Replace `receipt_path` with:

```php
'supplier_invoice_path' => 'nullable|string',
'payment_receipt_path' => 'nullable|string',
```

- [ ] **Step 2: `BillService` create/update**

Replace `receipt_path` assignments. After create, if invoice/receipt path set, insert matching `BillDocumentVersion` (`action=uploaded`). On update, preserve paths with `?? $bill->…` so omitted form fields do not wipe files.

- [ ] **Step 3: Receipt inbox + test**

Confirm payload:

```php
'supplier_invoice_path' => $ocrJob->file_path,
```

Assertion:

```php
$this->assertSame('receipts/test.jpg', $bill->supplier_invoice_path);
```

- [ ] **Step 4: Grep `receipt_path` under `app/` and fix writers** (`ProcessOcr`, `OCRService`, Copilot).

- [ ] **Step 5: Run**

```bash
/opt/homebrew/bin/php artisan test --filter=ReceiptInboxTest
```

Expected: PASS

- [ ] **Step 6: Skip commit** unless user asked

---

### Task 5: Controller + routes + feature tests

**Files:**
- Modify: `app/Http/Controllers/BillController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Bills/BillDocumentUploadTest.php`

**Interfaces:**
- `POST bills/upload-document` (`bills.upload-document`)
- `POST bills/upload-receipt` → supplier_invoice alias
- `GET bills/{id}/document` (`bills.document`)
- `GET bills/{id}/document-versions/{version}` (`bills.document-versions`)
- `GET bills/ocr-status` returns `phase`, `progress`, `label`
- JSON upload includes `apply_ocr` boolean (`true` only when draft invoice OCR should merge fields)

- [ ] **Step 1: Write failing feature tests**

Use `ReceiptInboxTest` tenant setup pattern. Cover:

1. `payment_receipt` upload does **not** dispatch `ProcessOcr`.
2. `supplier_invoice` upload **does** dispatch `ProcessOcr`.
3. Non-draft replace without `reason` → 422.
4. Non-draft replace with reason → current path updates; prior path remains in versions and is servable.
5. Clear after post rejected (422).
6. Posted invoice replace does not change bill totals; response `apply_ocr: false`.

Example:

```php
Queue::fake();
Storage::fake('public');
$file = UploadedFile::fake()->create('pay.pdf', 100, 'application/pdf');

$this->actingAs($this->user)
    ->post(route('bills.upload-document'), [
        'slot' => 'payment_receipt',
        'document' => $file,
        'bill_id' => $bill->id,
        'reason' => 'Paid via transfer',
    ])
    ->assertOk();

Queue::assertNotPushed(ProcessOcr::class);
$this->assertNotNull($bill->fresh()->payment_receipt_path);
```

- [ ] **Step 2: Run — expect FAIL**

```bash
/opt/homebrew/bin/php artisan test --filter=BillDocumentUploadTest
```

- [ ] **Step 3: Implement `uploadDocument` + alias + serve + OCR progress**

Validate:

```php
'slot' => 'required|in:supplier_invoice,payment_receipt',
'document' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
'bill_id' => 'nullable|exists:bills,id',
'reason' => 'nullable|string|max:500',
```

`uploadReceipt`: merge `slot=supplier_invoice`, map file field `receipt` → `document`, call `uploadDocument`.

OCR status pending branch:

```php
$startedAt = (int) $request->query('started_at', 0);
$elapsed = $startedAt > 0 ? max(0, (int) (microtime(true) * 1000) - $startedAt) : 5_000;

return response()->json([
    'status' => 'pending',
    ...\App\Support\OcrProgress::forPending($elapsed),
]);
```

Completed/failed merge `OcrProgress::completed()` / `failed()`.

Serve: reuse `sanitiseReceiptPath`; resolve current path from slot column; version route loads `BillDocumentVersion` for that bill.

- [ ] **Step 4: Register routes** next to existing bill OCR routes in `routes/web.php`.

- [ ] **Step 5: Run**

```bash
/opt/homebrew/bin/php artisan test --filter='BillDocumentUploadTest|ReceiptInboxTest|OcrProgressTest'
```

Expected: PASS

- [ ] **Step 6: Skip commit** unless user asked

---

### Task 6: Frontend — dual upload + phase progress

**Files:**
- Create: `resources/js/Components/BillDocumentUpload.jsx`
- Modify: `resources/js/Components/ReceiptUpload.jsx` (wrapper: `slot="supplier_invoice"`)
- Modify: `resources/js/Pages/Bills/_Form.jsx`
- Modify: `resources/js/Pages/Bills/Create.jsx`
- Modify: `resources/js/Pages/Bills/Edit.jsx`

**Interfaces:**
- Props: `slot`, `billId`, `requireReason`, `currentUrl`, `onComplete({ path, url, ocrData, applyOcr })`, `compact`
- Form keys: `supplier_invoice_path`, `payment_receipt_path`

- [ ] **Step 1: Build `BillDocumentUpload`**

1. If `requireReason`, modal collects reason before POST.
2. POST `bills.upload-document` (`slot`, `document`, `bill_id`, `reason`).
3. Upload % → overall 0–25 band; labels depend on slot.
4. Invoice pending: `startedAt = Date.now()`, poll `bills.ocr-status?path=&started_at=`, drive UI from `progress`/`label`/`phase`. Never freeze at 100 while pending.
5. Payment receipt: no OCR poll.
6. `onComplete`: parent merges OCR **only** if draft and `applyOcr !== false`.

Copy:

- Invoice: “Supplier invoice” / drop to fill bill
- Receipt: “Payment receipt (optional)” / proof of payment

- [ ] **Step 2: `_Form.jsx` two-slot grid**

```jsx
<div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
  <BillDocumentUpload slot="supplier_invoice" /* … */ compact />
  <BillDocumentUpload slot="payment_receipt" /* … */ compact />
</div>
```

Do **not** disable uploads when `!isDraft`. Set `requireReason={Boolean(billId) && !isDraft}`.

- [ ] **Step 3: Create/Edit data keys** — replace `receipt_path`.

- [ ] **Step 4:** `npm run build` — expect success

- [ ] **Step 5: Manual smoke** at `http://127.0.0.1:8011` — draft OCR progress moves; receipt no scan; post → replace asks reason; amounts unchanged

- [ ] **Step 6: Skip commit** unless user asked

---

### Task 7: Show history + Index/Audit

**Files:**
- Modify: `app/Http/Controllers/BillController.php` (show/edit props)
- Modify: `resources/js/Pages/Bills/Show.jsx`
- Modify: `resources/js/Pages/Bills/Index.jsx`
- Modify: `resources/js/Pages/Audit/Index.jsx`

**Interfaces:**
- `document_versions`: `{ id, slot, action, reason, created_at, uploader_name, url }`
- Current: `supplier_invoice_url`, `payment_receipt_url`

- [ ] **Step 1:** Eager-load `documentVersions.uploader` on show; map URLs via `bills.document-versions`.

- [ ] **Step 2:** Show “Supporting documents” + “Document history” (newest first).

- [ ] **Step 3:** Index/Audit use supplier invoice URL (receipt secondary if present). Remove broken `receipt_url` usage.

- [ ] **Step 4:**

```bash
npm run build
/opt/homebrew/bin/php artisan test --filter='BillDocumentUploadTest|ReceiptInboxTest|OcrProgressTest'
```

- [ ] **Step 5: Skip commit** unless user asked

---

### Task 8: Full regression + leftover cleanup

- [ ] **Step 1: Grep cleanup**

```bash
rg "receipt_path|receipt_url|Scanning receipt" --glob '!docs/**' --glob '!storage/**' --glob '!node_modules/**'
```

Fix remaining app/test/js references.

- [ ] **Step 2: Full suite**

```bash
/opt/homebrew/bin/php artisan test
npm run build
```

Expected: all green.

- [ ] **Step 3: Stop** — ask user before commit / PR / merge

---

## Spec coverage checklist

| Spec requirement | Task |
| --- | --- |
| Dual slots + path columns | 1, 3 |
| `bill_document_versions` + migrate old receipt | 1 |
| OCR invoice only; receipt optional | 5, 6 |
| Reason after post; no clear | 3, 5 |
| Posted OCR does not change amounts | 5, 6 |
| Phase progress % | 2, 5, 6 |
| Show history UI | 7 |
| Inbox / Copilot mapping | 4 |
| Compat `upload-receipt` | 5 |
| Spec tests | 2, 4, 5, 8 |

## Out of scope (do not implement)

- Multi-kind attachment library
- Mandatory payment receipt on pay
- Sales invoice attachment changes
- Full Receipt Inbox redesign
