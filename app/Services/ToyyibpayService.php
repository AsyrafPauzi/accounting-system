<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ToyyibpayService
{
    protected string $secretKey;
    protected string $categoryCode;
    protected string $baseUrl;

    public function __construct()
    {
        // Read strictly from config (which is gated to null on
        // self-hosted installs in config/services.php). The previous
        // version fell back to env() directly, which would have leaked
        // a real key on a self-hosted install if someone left
        // TOYYIBPAY_SECRET_KEY set in the env file by mistake.
        $this->secretKey    = (string) (config('services.toyyibpay.secret_key') ?? '');
        $this->categoryCode = (string) (config('services.toyyibpay.category_code') ?? '');
        $env = (string) (config('services.toyyibpay.env') ?? 'sandbox');

        $this->baseUrl = $env === 'production'
            ? 'https://toyyibpay.com/index.php/api'
            : 'https://dev.toyyibpay.com/index.php/api';
    }

    /**
     * Create a bill in Toyyibpay.
     *
     * @param array $data
     * @return string|null The payment URL or null on failure.
     */
    public function createBill(array $data): ?string
    {
        try {
            $payload = [
                'userSecretKey'            => $this->secretKey,
                'categoryCode'             => $this->categoryCode,
                'billName'                 => $data['billName'],
                'billDescription'          => $data['billDescription'],
                'billPriceSetting'         => 1,
                'billPayorInfo'            => 1, // Show payor info
                'billAmount'               => (int) round($data['billAmount'] * 100), // Convert to cents
                'billReturnUrl'            => $data['billReturnUrl'],
                'billCallbackUrl'          => $data['billCallbackUrl'],
                'billExternalReferenceNo'  => $data['billExternalReferenceNo'],
                'billTo'                   => $data['billTo'],
                'billEmail'                => $data['billEmail'],
                'billPhone'                => $data['billPhone'],
            ];

            Log::info('Toyyibpay createBill Request', ['payload' => array_diff_key($payload, ['userSecretKey' => ''])]);

            $response = Http::asForm()->post("{$this->baseUrl}/createBill", $payload);

            if ($response->successful()) {
                $result = $response->json();
                
                if (is_array($result) && isset($result[0]['BillCode'])) {
                    $billCode = $result[0]['BillCode'];
                    return "{$this->baseUrl}/../{$billCode}";
                }

                Log::error('Toyyibpay API Error (Unexpected format or empty)', [
                    'json' => $result,
                    'body' => $response->body()
                ]);
            } else {
                Log::error('Toyyibpay HTTP Error', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error('Toyyibpay Service Exception', ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Fetch bill transactions from ToyyibPay getBillTransactions API.
     *
     * @see https://toyyibpay.com/apireference/#get-bill-transactions
     *
     * @return list<array<string, mixed>>|null
     */
    public function getBillTransactions(string $billCode): ?array
    {
        if ($this->secretKey === '' || $billCode === '') {
            return null;
        }

        try {
            $response = Http::asForm()->post("{$this->baseUrl}/getBillTransactions", [
                'userSecretKey' => $this->secretKey,
                'billCode'      => $billCode,
            ]);

            if (! $response->successful()) {
                Log::warning('Toyyibpay getBillTransactions HTTP error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return null;
            }

            $result = $response->json();

            return is_array($result) ? $result : null;
        } catch (\Exception $e) {
            Log::error('Toyyibpay getBillTransactions exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Verify a bill was paid and the external reference matches the expected order id.
     */
    public function verifyPaidBill(string $billCode, string $expectedExternalRef): bool
    {
        $transactions = $this->getBillTransactions($billCode);
        if ($transactions === null || $transactions === []) {
            return false;
        }

        foreach ($transactions as $txn) {
            if (! is_array($txn)) {
                continue;
            }

            $status = (string) ($txn['billpaymentStatus'] ?? $txn['billPaymentStatus'] ?? '');
            $ref = (string) ($txn['billExternalReferenceNo'] ?? $txn['billExternalRefNo'] ?? '');

            if ($status === '1' && hash_equals($expectedExternalRef, $ref)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the payment URL from a bill code.
     */
    public function getPaymentUrl(string $billCode): string
    {
        return "{$this->baseUrl}/../{$billCode}";
    }
}
