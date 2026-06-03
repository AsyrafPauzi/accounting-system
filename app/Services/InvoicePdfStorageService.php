<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\UploadDisk;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfStorageService
{
    public function storagePath(Invoice $invoice): string
    {
        return 'invoices/'.$invoice->uuid.'.pdf';
    }

    public function forget(Invoice $invoice): void
    {
        $path = $this->storagePath($invoice);
        if (UploadDisk::disk()->exists($path)) {
            UploadDisk::disk()->delete($path);
        }
    }

    public function downloadResponse(Invoice $invoice, array $company, bool $attachment = true): Response
    {
        $path = $this->storagePath($invoice);
        $filename = "Invoice-{$invoice->invoice_number}.pdf";

        if (! $this->isCachedFresh($invoice, $path)) {
            $this->write($invoice, $company);
        }

        $disposition = $attachment ? 'attachment' : 'inline';

        return UploadDisk::disk()->response($path, $filename, [
            'Content-Type' => 'application/pdf',
        ], $disposition);
    }

    public function write(Invoice $invoice, array $company): void
    {
        $invoice->loadMissing(['items', 'customer']);

        $binary = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'customer' => $invoice->customer,
            'company' => $company,
        ])->setPaper('a4', 'portrait')->output();

        UploadDisk::disk()->put($this->storagePath($invoice), $binary);
    }

    private function isCachedFresh(Invoice $invoice, string $path): bool
    {
        if (! UploadDisk::disk()->exists($path)) {
            return false;
        }

        $fileTime = UploadDisk::disk()->lastModified($path);

        return $invoice->updated_at->getTimestamp() <= $fileTime;
    }
}
