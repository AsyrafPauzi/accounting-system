<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\SelfHostedInstall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Patch broadcaster + global settings for the SaaS super-admin
 * (lives on bukucloud.com / internal.bukucloud.com).
 *
 * Sets the "latest release version" advertised to every self-hosted
 * install via the heartbeat response. Customers' instances pick it
 * up on their next daily heartbeat and show an "update available"
 * banner in their UI.
 *
 * No license / no Docker push is sent over the wire — that's the
 * customer's deployment concern (e.g. Watchtower, manual `docker
 * compose pull`). We just *advertise* the version; the customer
 * decides when to upgrade.
 */
class PlatformSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $installs = SelfHostedInstall::query()
            ->whereNull('revoked_at')
            ->get(['latest_version', 'latest_heartbeat_at']);

        // Quick "fleet roll-up" so the operator sees, at a glance,
        // how many installs are still on the old version vs. the new.
        $latestVersion = PlatformSetting::get('latest_release_version');
        $atLatest   = $installs->where('latest_version', $latestVersion)->count();
        $behind     = $installs->where('latest_version', '!=', $latestVersion)->count();
        $unknown    = $installs->whereNull('latest_version')->count();

        return Inertia::render('Admin/Platform/Settings', [
            'settings' => [
                'latest_release_version' => $latestVersion,
                'update_notes'           => PlatformSetting::get('update_notes'),
                'latest_release_url'     => PlatformSetting::get('latest_release_url'),
            ],
            'fleet' => [
                'total_installs'     => $installs->count(),
                'at_latest'          => $atLatest,
                'behind'             => $behind,
                'unknown_version'    => $unknown,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'latest_release_version' => ['nullable', 'string', 'max:32'],
            'update_notes'           => ['nullable', 'string', 'max:5000'],
            'latest_release_url'     => ['nullable', 'url', 'max:500'],
        ]);

        PlatformSetting::set('latest_release_version', $validated['latest_release_version'] ?? null);
        PlatformSetting::set('update_notes',           $validated['update_notes'] ?? null);
        PlatformSetting::set('latest_release_url',     $validated['latest_release_url'] ?? null);

        Log::info('Platform: latest release version broadcast updated', [
            'version' => $validated['latest_release_version'] ?? null,
            'by'      => $request->user()?->id,
        ]);

        return back()->with('success', 'Update broadcast saved. Self-hosted installs will pick it up on their next heartbeat (≤ 24h).');
    }
}
