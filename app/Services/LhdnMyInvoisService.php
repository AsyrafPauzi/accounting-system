<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LhdnMyInvoisService
{
    private string $env;
    private string $baseUrl;
    private string $tokenUrl;
    private ?string $clientId;
    private ?string $clientSecret;

    public function __construct()
    {
        $this->env          = config('lhdn.env', 'sandbox');
        $this->baseUrl      = config("lhdn.{$this->env}.base_url");
        $this->tokenUrl     = config("lhdn.{$this->env}.token_url");
        $this->clientId     = config('lhdn.client_id');
        $this->clientSecret = config('lhdn.client_secret');
    }

    /**
     * Check whether LHDN credentials are configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Obtain an OAuth2 access token from LHDN (cached for 55 minutes).
     */
    public function getAccessToken(): string
    {
        $cacheKey = "lhdn_access_token_{$this->env}";

        return Cache::remember($cacheKey, 55 * 60, function () {
            $response = Http::asForm()->post($this->tokenUrl, [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope'         => 'InvoicingAPI',
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException(
                    'LHDN token error: ' . $response->body()
                );
            }

            return $response->json('access_token');
        });
    }

    /**
     * Submit an invoice document to LHDN MyInvois.
     * Updates the invoice's lhdn_* fields based on the API response.
     */
    public function submitDocument(Invoice $invoice): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'LHDN credentials not configured. Set LHDN_CLIENT_ID and LHDN_CLIENT_SECRET in .env'];
        }

        // Only posted invoices can be submitted
        if ($invoice->status === 'draft') {
            return ['success' => false, 'message' => 'Invoice must be posted to the General Ledger before submitting to LHDN.'];
        }

        if (in_array($invoice->lhdn_status, ['submitted', 'valid'], true)) {
            return ['success' => false, 'message' => 'Invoice is already submitted or valid at LHDN.'];
        }

        try {
            $token   = $this->getAccessToken();
            $payload = $this->buildUblPayload($invoice);

            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$this->baseUrl}/api/v1.1/documentsubmissions", [
                    'documents' => [$payload],
                ]);

            if (!$response->successful()) {
                $errorBody = $response->json();
                $message   = $errorBody['error']['message'] ?? $response->body();

                $invoice->update([
                    'lhdn_status'        => 'invalid',
                    'lhdn_error_message' => $message,
                ]);

                return ['success' => false, 'message' => "LHDN rejected the submission: {$message}"];
            }

            $body          = $response->json();
            $acceptedDocs  = $body['acceptedDocuments'] ?? [];
            $rejectedDocs  = $body['rejectedDocuments'] ?? [];

            if (!empty($rejectedDocs)) {
                $reason = $rejectedDocs[0]['error']['message'] ?? 'Unknown rejection reason';
                $invoice->update([
                    'lhdn_status'        => 'invalid',
                    'lhdn_error_message' => $reason,
                ]);
                return ['success' => false, 'message' => "LHDN rejected the document: {$reason}"];
            }

            $accepted       = $acceptedDocs[0] ?? [];
            $submissionUid  = $body['submissionUid'] ?? null;
            $uuid           = $accepted['uuid']   ?? null;
            $longId         = $accepted['longId']  ?? null;

            $invoice->update([
                'lhdn_status'          => 'submitted',
                'lhdn_uuid'            => $uuid,
                'lhdn_submission_uid'  => $submissionUid,
                'lhdn_long_id'         => $longId,
                'lhdn_submitted_at'    => now(),
                'lhdn_error_message'   => null,
            ]);

            return [
                'success'        => true,
                'message'        => 'Invoice successfully submitted to LHDN MyInvois.',
                'uuid'           => $uuid,
                'long_id'        => $longId,
                'submission_uid' => $submissionUid,
            ];

        } catch (\Throwable $e) {
            Log::error('[LHDN] Submit error', ['invoice' => $invoice->invoice_number, 'error' => $e->getMessage()]);
            $invoice->update(['lhdn_error_message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'LHDN API error: ' . $e->getMessage()];
        }
    }

    /**
     * Poll LHDN for the latest document status and update the invoice.
     */
    public function refreshDocumentStatus(Invoice $invoice): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'LHDN credentials not configured.'];
        }

        if (empty($invoice->lhdn_uuid)) {
            return ['success' => false, 'message' => 'Invoice has no LHDN UUID. Submit it first.'];
        }

        try {
            $token    = $this->getAccessToken();
            $uuid     = $invoice->lhdn_uuid;

            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->baseUrl}/api/v1.1/documents/{$uuid}/details");

            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Failed to fetch LHDN status: ' . $response->body()];
            }

            $data   = $response->json();
            $status = strtolower($data['status'] ?? 'submitted');

            // Normalise LHDN statuses to our internal values
            $statusMap = [
                'valid'            => 'valid',
                 'invalid'         => 'invalid',
                'cancelled'        => 'cancelled',
                'submitted'        => 'submitted',
                'inprogress'       => 'submitted',
            ];
            $mappedStatus = $statusMap[$status] ?? 'submitted';

            $invoice->update([
                'lhdn_status'      => $mappedStatus,
                'lhdn_long_id'     => $data['longId']  ?? $invoice->lhdn_long_id,
                'lhdn_error_message' => $mappedStatus === 'invalid' ? ($data['validationSteps'][0]['error']['message'] ?? null) : null,
            ]);

            return ['success' => true, 'message' => "Status updated to '{$mappedStatus}'.", 'status' => $mappedStatus];

        } catch (\Throwable $e) {
            Log::error('[LHDN] Refresh error', ['invoice' => $invoice->invoice_number, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'LHDN API error: ' . $e->getMessage()];
        }
    }

    /**
     * Cancel a submitted document at LHDN.
     */
    public function cancelDocument(Invoice $invoice, string $reason = 'Cancelled by user'): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'LHDN credentials not configured.'];
        }

        if (empty($invoice->lhdn_uuid)) {
            return ['success' => false, 'message' => 'Invoice has no LHDN UUID.'];
        }

        if (!in_array($invoice->lhdn_status, ['submitted', 'valid'], true)) {
            return ['success' => false, 'message' => 'Only submitted or valid documents can be cancelled.'];
        }

        try {
            $token = $this->getAccessToken();
            $uuid  = $invoice->lhdn_uuid;

            $response = Http::withToken($token)
                ->timeout(15)
                ->put("{$this->baseUrl}/api/v1.1/documents/state/{$uuid}/state", [
                    'status' => 'cancelled',
                    'reason' => $reason,
                ]);

            if (!$response->successful()) {
                $msg = $response->json('error.message') ?? $response->body();
                return ['success' => false, 'message' => "LHDN cancel error: {$msg}"];
            }

            $invoice->update([
                'lhdn_status'        => 'cancelled',
                'lhdn_error_message' => null,
            ]);

            return ['success' => true, 'message' => 'Document cancelled at LHDN.'];

        } catch (\Throwable $e) {
            Log::error('[LHDN] Cancel error', ['invoice' => $invoice->invoice_number, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'LHDN API error: ' . $e->getMessage()];
        }
    }

    /**
     * Build the UBL-compatible JSON document payload for LHDN submission.
     */
    private function buildUblPayload(Invoice $invoice): array
    {
        $invoice->load(['customer', 'items']);
        $company = DB::table('company_profiles')->first();

        $supplier = [
            'TIN'              => $company->tin ?? '',
            'BRN'              => $company->brn ?? '',
            'Name'             => $company->legal_name ?? $company->display_name ?? '',
            'AddressLine0'     => $company->street ?? '',
            'CityName'         => $company->city ?? '',
            'PostalZone'       => $company->postcode ?? '',
            'CountrySubentity' => $company->state ?? '',
            'CountryCode'      => 'MYS',
            'Phone'            => $company->phone ?? '',
        ];

        $customer = $invoice->customer;
        $buyer = [
            'TIN'              => $customer->tin ?? 'EI00000000010',
            'BRN'              => $customer->brn ?? '',
            'Name'             => $customer->name ?? '',
            'AddressLine0'     => $customer->street ?? '',
            'CityName'         => $customer->city ?? '',
            'PostalZone'       => $customer->postcode ?? '',
            'CountrySubentity' => $customer->state ?? '',
            'CountryCode'      => 'MYS',
            'Phone'            => $customer->phone ?? '',
        ];

        $lineItems = $invoice->items->map(function ($item, $idx) {
            return [
                'ID'                    => $idx + 1,
                'InvoicedQuantity'      => (float) $item->quantity,
                'LineExtensionAmount'   => (float) $item->amount,
                'Description'           => $item->description,
                'UnitPrice'             => (float) $item->unit_price,
                'TaxRate'               => (float) $item->tax_rate,
                'TaxAmount'             => round(($item->amount * $item->tax_rate) / 100, 2),
                'ClassificationCode'    => $item->item_classification ?? '022',
            ];
        })->values()->all();

        return [
            'format'       => 'JSON',
            'documentHash' => hash('sha256', $invoice->invoice_number . $invoice->total_amount),
            'codeNumber'   => $invoice->invoice_number,
            'document'     => [
                'ID'               => $invoice->invoice_number,
                'IssueDate'        => $invoice->issue_date,
                'IssueTime'        => '00:00:00Z',
                'InvoiceTypeCode'  => '01', // 01 = Invoice
                'DocumentCurrencyCode' => 'MYR',
                'TotalAmount'      => (float) $invoice->total_amount,
                'TaxAmount'        => (float) $invoice->tax_amount,
                'Supplier'         => $supplier,
                'Buyer'            => $buyer,
                'InvoiceLines'     => $lineItems,
                'MSICCode'         => $invoice->msic_code ?? '62010',
            ],
        ];
    }
}
