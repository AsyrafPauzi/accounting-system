# Bill dual documents + post-ledger audit

**Date:** 2026-08-30  
**Status:** Approved for planning  
**Goal:** Let bills attach both a supplier invoice and a payment receipt; keep OCR on the invoice; allow document replace after ledger post with retained file history and a required reason for transparent audit.

## Decisions locked

| Topic | Choice |
| --- | --- |
| Document model | **A** — Two fixed slots: supplier invoice + payment receipt |
| Storage approach | **Option 1** — Current path columns on `bills` + `bill_document_versions` history |
| OCR source | **Supplier invoice only** (not payment receipt) |
| Payment receipt | **Always optional** (encouraged in UI, never required to pay/post) |
| Post-ledger form fields | Stay locked (amounts/lines unchanged) |
| Post-ledger documents | Replace allowed; **reason required**; **clear forbidden** |
| OCR after post | File + version only — **do not** overwrite posted amounts from OCR |
| OCR UX | Phase-based progress % so scan does not look stuck after upload hits 100% |

## Problem

Today:

1. Bills have a single `receipt_path` used as an OCR “receipt” upload.
2. There is no separate place for the supplier’s invoice vs proof of payment.
3. After push to ledger, form fields lock, but the file endpoint can still overwrite `receipt_path`.
4. `Auditable` may log the path string change; the **old file is not kept** and there is no document history UI or replace reason.
5. `ReceiptUpload` progress % tracks **HTTP upload only**. During OCR poll the UI often sits at ~100% “Scanning…”, so users think it is stuck.

## Desired outcome

- Create/edit bill: upload **supplier invoice** (OCR autofill) and optionally **payment receipt**.
- After ledger: amounts stay immutable; either document can be **replaced** with who / when / reason / previous file retained.
- OCR progress shows meaningful phase % through upload → queue → scan → done/fail.

## Data model

### `bills` current pointers

| Column | Notes |
| --- | --- |
| `supplier_invoice_path` | Current supplier invoice file (OCR source) |
| `payment_receipt_path` | Current payment receipt file (nullable) |
| `ocr_status` / `ocr_data` | Unchanged semantics; tied to supplier invoice |

Migration:

1. Add the two path columns.
2. Copy existing `receipt_path` → `supplier_invoice_path`.
3. For each non-null migrated path, insert one `bill_document_versions` row (`slot=supplier_invoice`, `action=uploaded`, `reason=null`).
4. Drop `receipt_path` (or keep a read alias for one release if external callers need it; prefer drop + update all in-app references in the same ship).

Remove / update Bill model fillable, accessors (`receipt_url` → invoice/receipt URLs), Create/Edit/Show props, Receipt Inbox confirm, Copilot helpers that set `receipt_path`.

### `bill_document_versions` (tenant)

| Column | Notes |
| --- | --- |
| `id` | PK |
| `bill_id` | FK bills |
| `slot` | `supplier_invoice` \| `payment_receipt` |
| `path` | Stored path for **this** version’s file (never deleted on replace) |
| `original_filename` | Nullable |
| `mime` | Nullable |
| `size_bytes` | Nullable |
| `action` | `uploaded` \| `replaced` \| `cleared` |
| `reason` | Nullable; **required when bill status ≠ `draft`** |
| `uploaded_by` | FK users (nullable if system) |
| timestamps | `created_at` (immutable history) |

Rules:

- Current file for a slot = `bills.{slot}_path`.
- Each successful upload/replace/clear appends a version row.
- After post: `cleared` is not allowed; only `replaced` (and first `uploaded` if slot was empty).
- Draft: clear allowed → set path null + version `action=cleared`.

No change to journal/ledger tables. Sales `invoice_attachments` stays separate (sales-side pattern; bills use this dedicated versioned design).

## UI

### BillForm (`_Form.jsx` + shared upload component)

Same surface card, two slots (side-by-side desktop, stacked mobile):

1. **Supplier invoice** — drag/drop; OCR; autofill dates/supplier/lines.
2. **Payment receipt** — drag/drop; store only; no OCR; always optional.

After success: filename, View, Replace. Form field `disabled={!isDraft}` does **not** disable these slots.

Labels: stop calling the OCR file “Receipt”; use “Supplier invoice”. Payment slot copy: proof of payment (optional).

### Replace after ledger

Replace → modal: choose file + **required reason** (short text) → submit. Cancel leaves current file.

### Show page

- Links to current supplier invoice and payment receipt (if any).
- **Document history** panel: newest first — slot, action, user, time, reason, link to open that version’s file.

### OCR progress (supplier invoice only)

| Phase | Approx % | Label |
| --- | --- | --- |
| Upload | 0–25% | Uploading invoice… |
| Queued | 25–35% | Waiting for scan… |
| Processing | 35–90% | Scanning invoice… (ease upward while polling) |
| Done / Fail | 100% / error | Scan complete / failed — enter manually |

If the OCR provider does not return a real percent, map `ocr_status` / poll age to **stage + smooth estimated progress**. Never leave the UI frozen at 100% while status is still pending.

Payment-receipt upload: upload progress only (no scan phase).

## API

| Method | Route | Behaviour |
| --- | --- | --- |
| `POST` | `/bills/upload-document` | `slot`, file, optional `bill_id`, optional `reason`. Invoice → store + `ProcessOcr`. Receipt → store only. Non-draft + existing bill → validate reason, write version, update current path. |
| `POST` | `/bills/upload-receipt` | Thin alias → supplier invoice slot (compat for inbox / old clients) for one release if needed. |
| `GET` | `/bills/ocr-status` | Existing shape + optional `phase` / `progress` (0–100). |
| `GET` | `/bills/{id}/document` | `slot` query; serve current file (auth + path check). |
| `GET` | `/bills/{id}/document-versions/{version}` | Serve historical file. |

Inertia Show/Edit props include current URLs/paths and `document_versions` (newest first).

Permissions:

- Invoice OCR upload: reuse `plan.permission:ocr.use` (same as today).
- Payment receipt upload / view / history: same bill view/update gates as the bill itself.
- Serving files: authenticated tenant user with bill access; path must belong to that bill (current or a version row).

## Behaviour rules

1. Amounts/lines/header: locked after post (unchanged).
2. Documents: editable anytime; post-ledger replace requires reason; no clear after post.
3. Replacing supplier invoice on **draft**: re-run OCR and allow field merge via existing `onOcrComplete`.
4. Replacing supplier invoice on **posted** (or any non-draft): attach + version only; **ignore OCR field merge for amounts** (do not change posted bill).
5. Cash bills that post on create: treat as non-draft for reason-on-replace.
6. Receipt inbox → draft bill: map file to `supplier_invoice_path`.
7. File types/size: JPG, PNG, WebP, PDF · 10 MB · both slots.
8. Failed OCR: invoice file still attached; manual entry; draft can replace and retry.

## Out of scope

- Open-ended multi-file attachment library / arbitrary kinds beyond the two slots.
- Requiring payment receipt before recording payment.
- Changing posted journal amounts from OCR.
- Reworking sales `invoice_attachments`.
- Full rewrite of Receipt Inbox UI (only path mapping + labels as needed).

## Testing

- Dual upload; OCR job only for `supplier_invoice`.
- Draft replace/clear; version rows written.
- Non-draft replace without reason → 422.
- Non-draft replace with reason → current path updates; old path still servable via version.
- Non-draft clear → rejected.
- Posted invoice replace does not change bill amounts / journal.
- Migration copies `receipt_path` and seeds version.
- OCR status exposes progress/phase; UI never stuck at 100% while pending (feature or unit around progress mapping).

## Implementation notes

- Prefer a small `BillDocumentService` (or methods on `BillService`) for attach/replace/clear + versioning so controllers stay thin.
- Reuse storage disk/`receipts/` folder or rename to `bill-documents/` in the same ship; keep path sanitisation pattern from `showReceipt`.
- Update `ReceiptUpload` into a slot-aware component (or twin wrappers) rather than duplicating OCR poll logic.
- Existing `audit_logs` via `Auditable` may still record path attribute changes; **document history UI reads `bill_document_versions`**, not `audit_logs`.
