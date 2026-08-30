<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillDocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BillDocumentService
{
    public const SLOTS = ['supplier_invoice', 'payment_receipt'];

    public function __construct(
        protected ImageMetadataStripper $metadataStripper,
    ) {}

    public function pathColumn(string $slot): string
    {
        return match ($slot) {
            'supplier_invoice' => 'supplier_invoice_path',
            'payment_receipt' => 'payment_receipt_path',
            default => throw new \InvalidArgumentException('Invalid document slot.'),
        };
    }

    public function storeFile(UploadedFile $file): string
    {
        $tempPath = $file->getRealPath();
        if (is_string($tempPath) && $tempPath !== '') {
            $this->metadataStripper->strip($tempPath, $file->getMimeType());
        }

        $path = $file->store('receipts', 'public');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException(
                'Could not save the document. If using S3, check AWS_BUCKET and credentials; locally use FILESYSTEM_PUBLIC_DRIVER=local.'
            );
        }

        return $path;
    }

    public function attach(
        Bill $bill,
        string $slot,
        UploadedFile $file,
        ?string $reason,
        ?int $userId,
    ): BillDocumentVersion {
        $this->assertValidSlot($slot);
        $column = $this->pathColumn($slot);
        $isDraft = $bill->status === 'draft';

        if (! $isDraft && trim((string) $reason) === '') {
            throw ValidationException::withMessages([
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
            'size_bytes' => $file->getSize() ?: null,
            'action' => $action,
            'reason' => $isDraft ? $reason : trim((string) $reason),
            'uploaded_by' => $userId,
        ]);
    }

    public function recordInitialVersion(
        Bill $bill,
        string $slot,
        string $path,
        ?int $userId,
        ?string $originalFilename = null,
        ?string $mime = null,
        ?int $sizeBytes = null,
    ): BillDocumentVersion {
        $this->assertValidSlot($slot);

        return BillDocumentVersion::create([
            'bill_id' => $bill->id,
            'slot' => $slot,
            'path' => $path,
            'original_filename' => $originalFilename,
            'mime' => $mime,
            'size_bytes' => $sizeBytes,
            'action' => 'uploaded',
            'reason' => null,
            'uploaded_by' => $userId,
        ]);
    }

    public function clear(Bill $bill, string $slot, ?int $userId): void
    {
        $this->assertValidSlot($slot);

        if ($bill->status !== 'draft') {
            throw ValidationException::withMessages([
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

    private function assertValidSlot(string $slot): void
    {
        if (! in_array($slot, self::SLOTS, true)) {
            throw new \InvalidArgumentException('Invalid document slot.');
        }
    }
}
