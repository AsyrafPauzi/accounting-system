<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\DataExportBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataExportController extends Controller
{
    /**
     * One export every RATE_LIMIT_HOURS hours. Picked so a typical user
     * hitting "wait, I lost the file, give me another" once or twice in a
     * day still works, but a compromised session can't churn through full
     * dumps continuously.
     */
    private const RATE_LIMIT_HOURS = 24;

    public function __construct(private readonly DataExportBuilder $builder)
    {
    }

    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $rateLimited = $this->isRateLimited($user->data_exported_at);

        // Hand the front-end a short-lived signed URL instead of relying
        // on a CSRF-protected POST. A signed link is tamper-proof, has a
        // five-minute window (much shorter than a session), and lets the
        // React page render the download as a plain `<a href>` — which
        // means the browser handles the streamed response natively
        // without any of the Inertia/CSRF round-tripping that was
        // tripping up the previous form-based approach.
        $downloadUrl = $rateLimited
            ? null
            : URL::temporarySignedRoute(
                'settings.data_export.download',
                Carbon::now()->addMinutes(5),
            );

        return Inertia::render('Settings/DataExport', [
            'lastExportedAt'  => optional($user->data_exported_at)->toIso8601String(),
            'rateLimitHours'  => self::RATE_LIMIT_HOURS,
            'rateLimited'     => $rateLimited,
            'nextAvailableAt' => $this->nextAvailableAt($user->data_exported_at),
            'downloadUrl'     => $downloadUrl,
        ]);
    }

    /**
     * Build the export and stream it as a download. Cleanup happens in the
     * `deleteFileAfterSend` hook so the temp zip never lingers on disk.
     */
    public function download(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($this->isRateLimited($user->data_exported_at)) {
            abort(429, 'You can request another export in ' . self::RATE_LIMIT_HOURS . ' hours.');
        }

        try {
            $path = $this->builder->build($user);
        } catch (\Throwable $e) {
            Log::error('DataExport: failed to build', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'err' => $e->getMessage(),
            ]);
            abort(500, 'Could not build your data export. Please try again or email support.');
        }

        // Stamp the rate-limit *before* streaming. If the download fails
        // mid-flight the user can retry in 24h instead of immediately.
        $user->forceFill(['data_exported_at' => Carbon::now()])->save();

        $filename = sprintf(
            'bukucloud-data-export-%s-%s.zip',
            $user->tenant_id ?? 'account',
            Carbon::now()->format('Ymd-His'),
        );

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    private function isRateLimited(?Carbon $lastExportedAt): bool
    {
        if (! $lastExportedAt) {
            return false;
        }
        return $lastExportedAt->diffInHours(Carbon::now(), false) < self::RATE_LIMIT_HOURS;
    }

    private function nextAvailableAt(?Carbon $lastExportedAt): ?string
    {
        if (! $lastExportedAt) {
            return null;
        }
        $next = $lastExportedAt->copy()->addHours(self::RATE_LIMIT_HOURS);
        return $next->isPast() ? null : $next->toIso8601String();
    }
}
