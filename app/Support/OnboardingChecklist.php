<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;

final class OnboardingChecklist
{
    /**
     * @return array{visible: bool, dismissed: bool, completed: int, total: int, steps: list<array<string, mixed>>}
     */
    public static function forUser(User $user, ?Tenant $tenant): array
    {
        if (! $tenant || $user->isFirmUser()) {
            return self::empty();
        }

        $progress = [];
        if (\Illuminate\Support\Facades\Schema::connection($user->getConnectionName())->hasColumn('users', 'onboarding_steps')) {
            $raw = $user->getAttributes()['onboarding_steps'] ?? null;
            $progress = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);
        }
        if (! empty($progress['dismissed_at'])) {
            return self::empty();
        }

        $companyComplete = filled($tenant->legal_name) || filled(data_get($tenant->company, 'legal_name'));
        $hasCustomer = false;
        $hasPostedInvoice = false;
        $hasCollection = false;

        if (function_exists('tenancy') && tenancy()->initialized) {
            $hasCustomer = Customer::query()->exists();
            $hasPostedInvoice = Invoice::query()->whereNotIn('status', ['draft'])->exists();
            $hasCollection = Invoice::query()
                ->whereNotIn('status', ['draft', 'void'])
                ->where(function ($q) {
                    $q->where('amount_paid', '>', 0)
                        ->orWhereNotNull('sent_at')
                        ->orWhereNotNull('viewed_at');
                })
                ->exists();
        }

        $steps = [
            [
                'key'       => 'company',
                'label'     => 'Complete company profile',
                'done'      => $companyComplete,
                'href'      => route('settings.company', absolute: false),
            ],
            [
                'key'       => 'customer',
                'label'     => 'Add your first customer',
                'done'      => $hasCustomer,
                'href'      => route('customers.create', absolute: false),
            ],
            [
                'key'       => 'invoice',
                'label'     => 'Create and post your first invoice',
                'done'      => $hasPostedInvoice,
                'href'      => route('invoices.create', absolute: false),
            ],
            [
                'key'       => 'collect',
                'label'     => 'Record a payment or send the invoice',
                'done'      => $hasCollection,
                'href'      => route('invoices.index', absolute: false),
            ],
        ];

        $completed = count(array_filter($steps, fn ($s) => $s['done']));

        return [
            'visible'   => $completed < count($steps),
            'dismissed' => false,
            'completed' => $completed,
            'total'     => count($steps),
            'steps'     => $steps,
        ];
    }

    /**
     * @return array{visible: bool, dismissed: bool, completed: int, total: int, steps: list<array<string, mixed>>}
     */
    private static function empty(): array
    {
        return [
            'visible'   => false,
            'dismissed' => true,
            'completed' => 0,
            'total'     => 0,
            'steps'     => [],
        ];
    }
}
