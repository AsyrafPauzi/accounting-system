<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CommercePayService
{
    public function __construct(
        private string $username,
        private string $password,
        private string $secretKey,
        private string $baseUrl,
    ) {}

    public static function forTenant($tenant): ?self
    {
        if (
            ! $tenant
            || ! filled($tenant->commercepay_username ?? null)
            || ! filled($tenant->commercepay_password ?? null)
            || ! filled($tenant->commercepay_secret_key ?? null)
        ) {
            return null;
        }
        $live = filter_var($tenant->commercepay_live ?? false, FILTER_VALIDATE_BOOLEAN);
        $base = $live
            ? (string) config('services.commercepay.production_url')
            : (string) config('services.commercepay.staging_url');

        return new self(
            $tenant->commercepay_username,
            decrypt($tenant->commercepay_password),
            decrypt($tenant->commercepay_secret_key),
            rtrim($base, '/'),
        );
    }

    /**
     * Hosted checkout (no channelId) per CommercePay Request Payment docs.
     *
     * @param  array{amount: float, reference: string, description: string, return_url: string, callback_url: string, ip: string, name: string, email: string, phone: string, user_agent: string}  $data
     */
    public function requestPaymentUrl(array $data): ?string
    {
        $token = $this->authenticate();
        if (! $token) {
            return null;
        }

        $payload = [
            'currencyCode'  => 'MYR',
            'amount'        => (int) round($data['amount'] * 100),
            'referenceCode' => mb_substr($data['reference'], 0, 50),
            'description'   => mb_substr($data['description'], 0, 100),
            'ipAddress'     => $data['ip'],
            'userAgent'     => mb_substr($data['user_agent'], 0, 200),
            'returnUrl'     => $data['return_url'],
            'callbackUrl'   => $data['callback_url'],
            'customer'      => [
                'email'    => $data['email'],
                'mobileNo' => $data['phone'],
                'name'     => mb_substr($data['name'], 0, 36),
            ],
            'localCitizen'  => true,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'cap-signature' => hash_hmac('sha256', (string) $body, $this->secretKey),
            ])
            ->withBody((string) $body, 'application/json')
            ->post($this->baseUrl.'/api/services/app/PaymentGateway/RequestPayment');

        if (! $response->successful()) {
            Log::error('CommercePay RequestPayment failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $result = $response->json('result') ?? $response->json();
        $url = is_array($result) ? ($result['redirectUrl'] ?? $result['RedirectUrl'] ?? null) : null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function callbackIsPaid(array $payload): bool
    {
        $status = $payload['status'] ?? $payload['Status'] ?? null;

        return (string) $status === '1';
    }

    private function authenticate(): ?string
    {
        $response = Http::withHeaders([
            'Accept'     => 'application/json',
            'Secret-Key' => $this->secretKey,
        ])->post($this->baseUrl.'/api/TokenAuth/Authenticate', [
            'userNameOrEmailAddress' => $this->username,
            'password'               => $this->password,
        ]);

        if (! $response->successful()) {
            Log::error('CommercePay authenticate failed', ['status' => $response->status()]);

            return null;
        }

        $token = $response->json('result.accessToken')
            ?? $response->json('accessToken')
            ?? $response->json('access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
