<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SaaS super-admin per-tenant feature toggles.
 *
 * v1: just the accountant (Practice) feature. Stored on the tenant's
 * Stancl `data` JSON column as `practice_disabled` so we don't need
 * a schema migration — the column already exists and Stancl handles
 * the dot-notation mapping automatically.
 *
 * Disabling does *not* sever existing FirmClient links — that's a
 * separate destructive action and should require an explicit unlink
 * (so customers don't lose data on toggle). It only:
 *   - hides "Settings → Invite my accountant" inside the tenant
 *   - blocks new firm-to-tenant invites targeting this tenant
 *   - blocks new client-to-firm invites originating from this tenant
 */
class TenantFeatureController extends Controller
{
    public function togglePractice(Request $request, Tenant $tenant): RedirectResponse
    {
        $disabled = (bool) $request->boolean('disabled');

        $tenant->practice_disabled = $disabled;
        $tenant->save();

        Log::info('Platform: per-tenant practice feature toggled', [
            'tenant_id' => $tenant->id,
            'disabled'  => $disabled,
            'by'        => $request->user()?->id,
        ]);

        return back()->with(
            'success',
            $disabled
                ? 'Accountant feature disabled for '.($tenant->display_name ?: $tenant->id).'.'
                : 'Accountant feature re-enabled for '.($tenant->display_name ?: $tenant->id).'.'
        );
    }
}
