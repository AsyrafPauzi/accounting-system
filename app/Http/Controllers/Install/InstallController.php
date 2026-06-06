<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\Firm;
use App\Models\User;
use App\Services\Licensing\LicenseService;
use App\Support\Deployment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-run install wizard for self-hosted installs.
 *
 * Flow:
 *   1. License-gate middleware redirects unconfigured installs here.
 *   2. Customer pastes their license key (verified server-side).
 *   3. Customer creates the admin user.
 *   4. We persist the license key into a writable env bag, run the
 *      bootstrap command, log the admin in, and ship them to the
 *      dashboard.
 *
 * Once the install is complete and an admin user exists, this route
 * becomes inert (it returns a "already installed" page on subsequent
 * visits, regardless of whether someone is logged in) so a customer
 * can't accidentally re-run the wizard from their browser bookmark.
 *
 * The wizard is SaaS-mode-disabled — there's no install wizard on
 * SaaS, the platform admins handle bootstrapping centrally.
 */
class InstallController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        abort_unless(Deployment::isSelfHosted(), 404);

        if ($this->alreadyInstalled()) {
            return Inertia::render('Install/AlreadyInstalled');
        }

        return Inertia::render('Install/Setup', [
            'envWritable'  => $this->envIsWritable(),
            'licenseStatus'=> app(LicenseService::class)->status(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Deployment::isSelfHosted(), 404);
        abort_if($this->alreadyInstalled(), 409, 'Install already complete.');

        $validated = $request->validate([
            'license_key'           => ['required', 'string', 'min:50', 'max:4000'],
            'admin_name'            => ['required', 'string', 'max:255'],
            'admin_email'           => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'admin_password'        => ['required', 'confirmed', Rules\Password::defaults()],
            'company_name'          => ['nullable', 'string', 'max:200'],
        ]);

        // 1. Persist the license key into .env so subsequent boots
        // pick it up. We swap the value if the line exists, append
        // it otherwise. This is a write-once operation: the bootstrap
        // step below would fail if the env wasn't actually updated,
        // because the LicenseService re-reads config on the next call.
        $writeOk = $this->writeEnv('APP_LICENSE_KEY', $validated['license_key']);
        if (! $writeOk) {
            return back()->withErrors(['license_key' => 'Could not write to .env. Make sure the file is writable by the application user, or set APP_LICENSE_KEY manually before retrying.']);
        }

        // Make the new value visible to the running process so the
        // bootstrap command sees a valid license.
        config(['deployment.license_key' => $validated['license_key']]);
        app(LicenseService::class)->flush();

        $check = app(LicenseService::class)->evaluate();
        if (($check['status'] ?? '') !== 'valid') {
            return back()->withErrors(['license_key' => 'License key is not valid: '.($check['status'] ?? 'unknown').'. Double-check the key and try again.']);
        }

        // Decide which bootstrap flavour to run based on the license
        // features. Enterprise licenses (those that carry the
        // `practice.console` feature) get a firm + firm-owner with no
        // default client tenant — the operator adds clients later via
        // the Practice console. Standard licenses get the classic
        // default-tenant + admin-user setup.
        $features = is_array($check['claims']['features'] ?? null)
            ? (array) $check['claims']['features']
            : [];
        $firmMode = in_array('practice.console', $features, true);

        // 2. Bootstrap via the artisan command (idempotent).
        $exit = Artisan::call('self-hosted:bootstrap', array_filter([
            '--email'        => $validated['admin_email'],
            '--name'         => $validated['admin_name'],
            '--password'     => $validated['admin_password'],
            '--company-name' => $validated['company_name'] ?? ($firmMode ? 'My Firm' : 'My Company'),
            '--firm-mode'    => $firmMode ? true : null,
        ], fn ($v) => $v !== null));
        if ($exit !== 0) {
            return back()->withErrors(['admin_email' => 'Bootstrap failed: '.trim(Artisan::output()).' Check the application logs for details.']);
        }

        // 3. Log the admin in and ship them to the right home page.
        // Firm-owners go to /practice (their console); regular tenant
        // admins go to /dashboard.
        $admin = User::where('email', $validated['admin_email'])->first();
        if ($admin) {
            Auth::login($admin);
        }

        $home = $firmMode ? 'practice.dashboard' : 'dashboard';
        return redirect()->route($home)->with('success', 'Welcome to BukuCloud. Your install is ready.');
    }

    /**
     * "Already installed" sentinel. Two acceptable shapes:
     *
     *   1. Standard install → at least one user in the default tenant.
     *   2. Enterprise install → at least one Firm row exists.
     *
     * We deliberately do NOT key off "license key configured" because
     * someone might rotate the key without re-running the wizard.
     */
    private function alreadyInstalled(): bool
    {
        if (User::where('tenant_id', Deployment::DEFAULT_TENANT_ID)->exists()) {
            return true;
        }
        try {
            return Firm::query()->exists();
        } catch (\Throwable $e) {
            // Pre-migration installs (table absent) — treat as not
            // installed so the wizard renders normally.
            return false;
        }
    }

    private function envIsWritable(): bool
    {
        $path = base_path('.env');
        return is_writable($path) || (file_exists($path) === false && is_writable(base_path()));
    }

    /**
     * Atomic-ish .env write: swap a single line if it exists, append
     * otherwise. Not transactional — but we only run it once per
     * install, so the cost of "we wrote it but the controller died
     * before responding" is just a manual restart.
     */
    private function writeEnv(string $key, string $value): bool
    {
        $path = base_path('.env');
        if (! file_exists($path)) {
            // Create a minimal .env from scratch — the Docker image
            // ships .env.example pre-renamed, but in case someone
            // mounts an empty volume we still cope.
            $created = @file_put_contents($path, "APP_DEPLOYMENT_MODE=self_hosted\n");
            if ($created === false) return false;
        }

        $contents = file_get_contents($path);
        if ($contents === false) return false;

        $line = $key.'='.$this->escape($value);
        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)) {
            $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents);
        } else {
            $contents = rtrim($contents, "\n")."\n".$line."\n";
        }

        return @file_put_contents($path, $contents) !== false;
    }

    /**
     * Quote env values that contain spaces / # / $; bare values are
     * left as-is so they're easy to read by humans editing the file.
     */
    private function escape(string $value): string
    {
        if ($value === '') return '""';
        if (preg_match('/[\s#"\'$]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }
        return $value;
    }
}
