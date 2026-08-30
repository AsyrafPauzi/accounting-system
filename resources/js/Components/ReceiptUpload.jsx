import React from 'react';
import BillDocumentUpload from '@/Components/BillDocumentUpload';

/** @deprecated Prefer BillDocumentUpload with slot="supplier_invoice" */
export default function ReceiptUpload({ onOcrComplete, billId = null, compact = false }) {
    return (
        <BillDocumentUpload
            slot="supplier_invoice"
            billId={billId}
            compact={compact}
            onComplete={({ path, url, ocrData, applyOcr }) => {
                if (!onOcrComplete) return;
                if (applyOcr === false) {
                    onOcrComplete(null, url, path);
                    return;
                }
                onOcrComplete(ocrData, url, path);
            }}
        />
    );
}
