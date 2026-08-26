<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillplzService
{
    public function __construct(
        private string $secretKey,
        private string $collectionId,
        private string $baseUrl,
        private ?string $xSignatureKey = null,
    ) {}

    public static function forTenant($tenant): ?self
    {
        if (! $tenant || ! filled($tenant->billplz_secret_key) || ! filled($tenant->billplz_collection_id)) {
            return null;
        }
        $secret = decrypt($tenant->billplz_secret_key);
        $sandbox = filter_var($tenant->billplz_sandbox ?? true, FILTER_VALIDATE_BOOLEAN);
        $base = $sandbox
            ? 'https://www.billplz-sandbox.com/api'
            : 'https://www.billplz.com/api';
        $xsig = filled($tenant->billplz_xsignature_key ?? null)
            ? decrypt($tenant->billplz_xsignature_key)
            : null;

        return new self($secret, $tenant->billplz_collection_id, $base, $xsig);
    }

    public static function forPlatform(): ?self
    {
        $secret = (string) (config('services.billplz.secret_key') ?? '');
        $collection = (string) (config('services.billplz.collection_id') ?? '');
        if ($secret === '' || $collection === '') {
            return null;
        }
        $sandbox = (bool) config('services.billplz.sandbox', true);
        $base = $sandbox
            ? 'https://www.billplz-sandbox.com/api'
            : 'https://www.billplz.com/api';
        $xsig = config('services.billplz.xsignature_key');

        return new self($secret, $collection, $base, filled($xsig) ? (string) $xsig : null);
    }

    /**
     * @param  array{description: string, email: string, name: string, amount: float, callback_url: string, redirect_url: string, reference: string}  $data
     */
    public function createBill(array $data): ?string
    {
        $detailed = $this->createBillDetailed($data);

        return $detailed['url'] ?? null;
    }

    /**
     * @param  array{description:string,email:string,name:string,amount:float,callback_url:string,redirect_url:string,reference:string}  $data
     * @return array{id:string,url:string}|null
     */
    public function createBillDetailed(array $data): ?array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->asForm()
            ->post($this->baseUrl.'/v3/bills', [
                'collection_id'      => $this->collectionId,
                'description'        => mb_substr($data['description'], 0, 200),
                'email'              => $data['email'],
                'name'               => $data['name'],
                'amount'             => (int) round($data['amount'] * 100),
                'callback_url'       => $data['callback_url'],
                'redirect_url'       => $data['redirect_url'],
                'reference_1_label'  => 'Invoice',
                'reference_1'        => $data['reference'],
            ]);

        if (! $response->successful()) {
            Log::error('Billplz create bill failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $json = $response->json();
        $id = is_array($json) ? ($json['id'] ?? null) : null;
        $url = is_array($json) ? ($json['url'] ?? null) : null;

        if (! is_string($id) || $id === '' || ! is_string($url) || $url === '') {
            return null;
        }

        return ['id' => $id, 'url' => $url];
    }

    public function callbackIsPaid(array $payload): bool
    {
        if (! $this->xSignatureKey) {
            return false;
        }

        $incoming = (string) ($payload['x_signature'] ?? '');
        if ($incoming === '' || ! hash_equals($this->expectedXSignature($payload), $incoming)) {
            return false;
        }

        $paid = $payload['paid'] ?? false;

        return $paid === true || $paid === 'true' || $paid === '1' || $paid === 1;
    }

    /**
     * Billplz X-Signature: sort source fields, join keyvalue|keyvalue, HMAC-SHA256.
     *
     * @see https://www.billplz.com/api
     */
    private function expectedXSignature(array $payload): string
    {
        unset($payload['x_signature']);
        ksort($payload);
        $parts = [];
        foreach ($payload as $key => $value) {
            if ($value === null) {
                continue;
            }
            $parts[] = $key.((string) $value);
        }

        return hash_hmac('sha256', implode('|', $parts), (string) $this->xSignatureKey);
    }
}
