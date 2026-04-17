<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Process\Process;

class TenantAdminController extends Controller
{
    /**
     * Simple admin view of all tenants and their databases.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user() && $request->user()->hasRole('super-admin'), 403);

        $tenants = Tenant::all()->map(function (Tenant $tenant) {
            $dbName = method_exists($tenant, 'database') ? $tenant->database()->getName() : null;
            $owner = User::where('tenant_id', $tenant->getKey())->orderBy('id')->first();

            return [
                'id' => $tenant->getKey(),
                'name' => $tenant->getKey(),
                'database' => $dbName,
                'owner' => $owner ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                ] : null,
            ];
        });

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Impersonate a tenant's user (log in as them).
     */
    public function impersonate(Request $request, int $userId): RedirectResponse
    {
        abort_unless($request->user() && $request->user()->hasRole('super-admin'), 403);

        $user = User::findOrFail($userId);

        // Remember the original admin so we can restore later if needed.
        if (! $request->session()->has('impersonator_id')) {
            $request->session()->put('impersonator_id', $request->user()->id);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * Stop impersonation and return to the original admin user.
     */
    public function stopImpersonating(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');

        abort_unless($impersonatorId, 403);

        Auth::loginUsingId($impersonatorId);
        $request->session()->regenerate();

        return redirect()->route('admin.tenants.index');
    }

    /**
     * Export tenant database as a SQL dump (admin only). Saves a copy for retrieval/backup.
     */
    public function backup(Request $request, Tenant $tenant)
    {
        abort_unless($request->user() && $request->user()->hasRole('super-admin'), 403);

        $dbName = $tenant->database()->getName();
        $connection = Config::get('tenancy.database.central_connection', Config::get('database.default'));
        $config = Config::get("database.connections.{$connection}");

        if (($config['driver'] ?? '') === 'mysql') {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? '3306';
            $username = $config['username'] ?? 'root';
            $password = $config['password'] ?? '';
            $tmpFile = storage_path('app/tenant_backup_' . $tenant->getKey() . '_' . date('Y-m-d_His') . '.sql');
            $args = [
                'mysqldump',
                '-h', $host,
                '-P', $port,
                '-u', $username,
                $dbName,
            ];
            if ($password !== '') {
                $args[] = '--password=' . $password;
            }
            $process = new Process($args);
            $process->setTimeout(120);
            $process->run();
            if (!$process->isSuccessful()) {
                return redirect()->route('admin.tenants.index')
                    ->with('error', 'Backup failed: ' . $process->getErrorOutput());
            }
            file_put_contents($tmpFile, $process->getOutput());
            $filename = 'tenant-' . $tenant->getKey() . '-backup-' . date('Y-m-d_His') . '.sql';
            return response()->download($tmpFile, $filename)->deleteFileAfterSend(true);
        }

        if (($config['driver'] ?? '') === 'sqlite') {
            $tenantPath = $dbName && str_starts_with($dbName, '/') ? $dbName : database_path($dbName ?: ('tenant_' . $tenant->getKey() . '.sqlite'));
            if (!file_exists($tenantPath)) {
                return redirect()->route('admin.tenants.index')
                    ->with('error', 'Tenant database file not found.');
            }
            $filename = 'tenant-' . $tenant->getKey() . '-backup-' . date('Y-m-d_His') . '.sqlite';
            return response()->download($tenantPath, $filename);
        }

        return redirect()->route('admin.tenants.index')
            ->with('error', 'Backup is only supported for MySQL and SQLite.');
    }

    /**
     * Delete the tenant and its database (admin only). TenantDeleted event runs DeleteDatabase job.
     * Central records (users, subscriptions) for this tenant are removed first.
     */
    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($request->user() && $request->user()->hasRole('super-admin'), 403);

        $tenantId = $tenant->getKey();
        User::where('tenant_id', $tenantId)->delete();
        Subscription::where('tenant_id', $tenantId)->delete();
        $tenant->delete();

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant and its database have been deleted.');
    }
}

