<?php

namespace App\Services;

use App\Jobs\SendEstimateEmail;
use App\Jobs\SendInvoiceEmail;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use ZipArchive;

class DocumentBulkService
{
    public const MAX_IDS = 50;

    /**
     * @return list<string>
     */
    public function recipientsFor(Customer $customer): array
    {
        if (($customer->invoice_delivery_method ?? 'email') === 'none') {
            return [];
        }

        $customer->loadMissing('contacts');
        $billing = $customer->contacts
            ->where('type', 'billing')
            ->filter(fn ($c) => $c->email && filter_var($c->email, FILTER_VALIDATE_EMAIL))
            ->pluck('email')
            ->unique()
            ->values()
            ->all();
        if ($billing !== []) {
            return $billing;
        }
        if ($customer->email && filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            return [$customer->email];
        }

        return [];
    }

    /**
     * @param  list<int>  $ids
     * @return array{queued: int, skipped: int}
     */
    public function queueInvoiceEmails(array $ids, array $company): array
    {
        $queued = 0;
        $skipped = 0;
        foreach ($this->invoices($ids) as $invoice) {
            $customer = $invoice->customer;
            if (! $customer) {
                $skipped++;
                continue;
            }
            $recipients = $this->recipientsFor($customer);
            if ($recipients === []) {
                $skipped++;
                continue;
            }
            $invoice->forceFill([
                'last_emailed_status' => 'pending',
                'last_emailed_at'     => now(),
                'last_emailed_error'  => null,
                'last_emailed_to'     => implode(',', $recipients),
            ])->save();
            SendInvoiceEmail::dispatch($invoice->id, $recipients, $company);
            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /**
     * @param  list<int>  $ids
     * @return array{queued: int, skipped: int}
     */
    public function queueEstimateEmails(array $ids, array $company): array
    {
        $queued = 0;
        $skipped = 0;
        foreach ($this->estimates($ids) as $estimate) {
            $customer = $estimate->customer;
            if (! $customer) {
                $skipped++;
                continue;
            }
            $recipients = $this->recipientsFor($customer);
            if ($recipients === []) {
                $skipped++;
                continue;
            }
            $estimate->forceFill([
                'last_emailed_status' => 'pending',
                'last_emailed_at'     => now(),
                'last_emailed_error'  => null,
                'last_emailed_to'     => implode(',', $recipients),
            ])->save();
            SendEstimateEmail::dispatch($estimate->id, $recipients, $company);
            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /**
     * @param  list<int>  $ids
     */
    public function zipInvoicePdfs(array $ids, array $company): string
    {
        return $this->zip('invoices', $this->invoices($ids), function (Invoice $invoice) use ($company) {
            $invoice->loadMissing(['items', 'customer']);

            return [
                'Invoice-'.$invoice->invoice_number.'.pdf',
                Pdf::loadView('pdf.invoice', [
                    'invoice'  => $invoice,
                    'customer' => $invoice->customer,
                    'company'  => $company,
                ])->setPaper('a4', 'portrait')->output(),
            ];
        });
    }

    /**
     * @param  list<int>  $ids
     */
    public function zipEstimatePdfs(array $ids, array $company): string
    {
        return $this->zip('estimates', $this->estimates($ids), function (Estimate $estimate) use ($company) {
            $estimate->loadMissing(['items', 'customer']);

            return [
                'Estimate-'.$estimate->estimate_number.'.pdf',
                Pdf::loadView('pdf.estimate', [
                    'estimate' => $estimate,
                    'customer' => $estimate->customer,
                    'company'  => $company,
                ])->setPaper('a4', 'portrait')->output(),
            ];
        });
    }

    public function companyDetails(): array
    {
        $company = config('invoice.company') ?? [];
        if (function_exists('tenant') && tenant()) {
            return tenant()->getCompanyDetails();
        }
        if (auth()->check() && auth()->user()->tenant_id) {
            $tenant = Tenant::find(auth()->user()->tenant_id);
            if ($tenant) {
                return $tenant->getCompanyDetails();
            }
        }

        return $company;
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    public function sanitizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id) => $id > 0
        )));
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Invoice>
     */
    private function invoices(array $ids): Collection
    {
        return Invoice::with(['customer.contacts', 'items'])
            ->whereIn('id', array_slice($this->sanitizeIds($ids), 0, self::MAX_IDS))
            ->get();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Estimate>
     */
    private function estimates(array $ids): Collection
    {
        return Estimate::with(['customer.contacts', 'items'])
            ->whereIn('id', array_slice($this->sanitizeIds($ids), 0, self::MAX_IDS))
            ->get();
    }

    /**
     * @param  Collection<int, mixed>  $docs
     * @param  callable(mixed): array{0: string, 1: string}  $pdf
     */
    private function zip(string $prefix, Collection $docs, callable $pdf): string
    {
        if ($docs->isEmpty()) {
            throw new \LogicException('Select at least one document.');
        }

        $path = storage_path('app/tmp/'.$prefix.'-'.uniqid('', true).'.zip');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP file.');
        }
        foreach ($docs as $doc) {
            [$name, $bytes] = $pdf($doc);
            $zip->addFromString($this->safeName($name), $bytes);
        }
        $zip->close();

        return $path;
    }

    private function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'document.pdf';
    }
}
