<?php

namespace App\Services;

use App\Models\Bill;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OCRService
{
    /**
     * Process a receipt image and extract data.
     * 
     * @param string $filePath Path to the uploaded receipt
     * @return array Extracted data
     */
    public function process(string $filePath): array
    {
        // Simulate OCR processing delay
        // sleep(2);

        // In a real implementation, you would call an external API or use a library like Tesseract
        // For now, we'll return a mock response based on the "visual" presence of data
        
        return [
            'status' => 'success',
            'data' => [
                'supplier_name' => 'Mock Supplier Co.',
                'bill_date' => now()->format('Y-m-d'),
                'total_amount' => 125.50,
                'tax_amount' => 7.53,
                'currency' => 'MYR',
                'reference' => 'RCPT-' . Str::upper(Str::random(6)),
                'items' => [
                    ['description' => 'Office Supplies', 'amount' => 100.00],
                    ['description' => 'Shipping', 'amount' => 25.50],
                ]
            ]
        ];
    }

    /**
     * Update a bill with OCR results.
     */
    public function updateBillWithOCR(Bill $bill, array $ocrResult): void
    {
        if ($ocrResult['status'] === 'success') {
            $data = $ocrResult['data'];
            
            $bill->update([
                'ocr_status' => 'completed',
                'ocr_data' => $data,
                // We don't automatically overwrite everything unless the user confirms, 
                // but we store it for the auto-fill feature.
            ]);
        } else {
            $bill->update(['ocr_status' => 'failed']);
        }
    }
}
