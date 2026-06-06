<?php

namespace App\Mail;

use App\Models\Estimate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Customer-facing estimate email.
 *
 * Mirrors `App\Mail\InvoiceEmail` so the queue job and view scaffolding
 * stays consistent. Differences vs invoice:
 *   - Subject says "Estimate" / "Quotation".
 *   - The signed download URL points at `public.estimates.download`,
 *     not the invoice equivalent — keeps a clean separation between
 *     pre-sale (estimate) and post-sale (invoice) document trails.
 */
class EstimateEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Estimate $estimate;
    public array $company;

    public function __construct(Estimate $estimate, array $company)
    {
        $this->estimate = $estimate->loadMissing(['items', 'customer']);
        $this->company = $company;
    }

    public function build()
    {
        $estimate = $this->estimate;
        $customer = $estimate->customer;
        $company = $this->company;

        $subjectFormat = config('invoice.email.subject_format_estimate', 'Estimate :number from :company');
        $subject = strtr($subjectFormat, [
            ':number'  => $estimate->estimate_number,
            ':company' => $company['name'] ?? config('app.name'),
        ]);

        // Signed URL valid for 30 days — same TTL invoices use, so the
        // recipient has a comfortable window to download even if they
        // sit on the email for a while. The route resolves the tenant
        // database from the `tenant_id` query param so signed links
        // keep working from outside the app shell.
        $tenantId = function_exists('tenant') && tenant()
            ? tenant('id')
            : (auth()->user()->tenant_id ?? null);

        $downloadUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'public.estimates.download',
            now()->addDays(30),
            [
                'uuid'      => $estimate->uuid,
                'tenant_id' => $tenantId,
            ]
        );

        return $this->from(config('mail.from.address'), $company['name'] ?? config('app.name'))
            ->subject($subject)
            ->view('emails.estimate', [
                'estimate'     => $estimate,
                'customer'     => $customer,
                'company'      => $company,
                'download_url' => $downloadUrl,
            ]);
    }
}
