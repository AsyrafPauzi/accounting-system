<?php

namespace App\Console\Commands;

use App\Mail\ClientInviteFirm;
use App\Mail\CustomerStatementEmail;
use App\Mail\FirmInviteExistingClient;
use App\Mail\TeamMemberWelcome;
use App\Models\Customer;
use App\Models\Firm;
use App\Models\FirmInvitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailSmokeCommand extends Command
{
    protected $signature = 'mail:smoke
        {--to=asyraf.pauzi@hirix.ai : Recipient address for all smoke emails}
        {--skip-notifications : Skip Laravel built-in password reset and email verification notifications}';

    protected $description = 'Send representative BukuCloud emails through the configured mailer.';

    public function handle(): int
    {
        $to = (string) $this->option('to');

        $this->info('Mailer: '.config('mail.default'));
        $this->info('From: '.config('mail.from.address').' ('.config('mail.from.name').')');
        $this->info('To: '.$to);

        try {
            $tenant = $this->sampleTenant();
            $firm = $this->sampleFirm();
            $invitation = $this->sampleInvitation($tenant);
            $user = $this->sampleUser($to, $tenant);

            Mail::to($to)->send(new CustomerStatementEmail(
                customer: $this->sampleCustomer($to),
                statement: [
                    'from' => now()->startOfMonth()->toDateString(),
                    'to' => now()->toDateString(),
                    'opening_balance' => 0,
                    'total_charges' => 0,
                    'total_payments' => 0,
                    'total_credits' => 0,
                    'closing_balance' => 0,
                    'items' => [],
                ],
                company: [
                    'name' => 'BukuCloud Smoke Test',
                    'email' => config('mail.from.address'),
                ],
                pdfBytes: "%PDF-1.4\n% BukuCloud smoke test statement\n",
                pdfFilename: 'bukucloud-smoke-statement.pdf',
            ));
            $this->line('✓ Customer statement email sent');
            usleep(700_000);

            Mail::to($to)->send(new ClientInviteFirm(
                tenant: $tenant,
                invitation: $invitation,
                inviterName: 'BukuCloud Smoke Test',
            ));
            $this->line('✓ SME → firm invitation email sent');
            usleep(700_000);

            Mail::to($to)->send(new FirmInviteExistingClient(
                firm: $firm,
                invitation: $invitation,
                inviterName: 'BukuCloud Smoke Test',
            ));
            $this->line('✓ Firm → existing client invitation email sent');
            usleep(700_000);

            Mail::to($to)->send(new TeamMemberWelcome(
                user: $user,
                tenant: $tenant,
                role: 'accountant',
                resetUrl: route('password.reset', ['token' => 'smoke-token', 'email' => $to]),
                inviterName: 'BukuCloud Smoke Test',
            ));
            $this->line('✓ Team member welcome email sent');
            usleep(700_000);

            if (! $this->option('skip-notifications')) {
                $user->notify(new ResetPassword('smoke-token'));
                $this->line('✓ Password reset notification sent');
                usleep(700_000);

                $user->notify(new VerifyEmail());
                $this->line('✓ Email verification notification sent');
            }
        } catch (\Throwable $e) {
            $this->error('Mail smoke failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Mail smoke complete.');
        return self::SUCCESS;
    }

    private function sampleTenant(): Tenant
    {
        return (new Tenant())->forceFill([
            'id' => 'smoke-tenant',
            'data' => [
                'display_name' => 'BukuCloud Smoke Company',
                'legal_name' => 'BukuCloud Smoke Company Sdn Bhd',
            ],
        ]);
    }

    private function sampleFirm(): Firm
    {
        return (new Firm())->forceFill([
            'id' => 1,
            'name' => 'BukuCloud Smoke Accounting Firm',
            'slug' => 'smoke-firm',
            'status' => 'active',
        ]);
    }

    private function sampleInvitation(Tenant $tenant): FirmInvitation
    {
        return (new FirmInvitation())->forceFill([
            'id' => 1,
            'tenant_id' => $tenant->id,
            'direction' => FirmInvitation::DIRECTION_CLIENT_TO_FIRM,
            'email' => 'smoke@example.test',
            'token' => FirmInvitation::generateToken(),
            'permission_level' => 'admin',
            'status' => FirmInvitation::STATUS_PENDING,
            'expires_at' => FirmInvitation::defaultExpiresAt(),
        ]);
    }

    private function sampleUser(string $email, Tenant $tenant): User
    {
        return (new User())->forceFill([
            'id' => 999999,
            'name' => 'BukuCloud Smoke Recipient',
            'email' => $email,
            'tenant_id' => $tenant->id,
        ]);
    }

    private function sampleCustomer(string $email): Customer
    {
        return (new Customer())->forceFill([
            'id' => 1,
            'name' => 'BukuCloud Smoke Customer',
            'email' => $email,
            'currency' => 'MYR',
        ]);
    }
}
