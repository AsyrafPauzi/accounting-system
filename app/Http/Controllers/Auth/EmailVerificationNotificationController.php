<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            $home = $request->user()->isFirmUser() ? 'practice.dashboard' : 'dashboard';
            return redirect()->intended(route($home, absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::error('Email verification notification failed', [
                'user_id' => $request->user()->id,
                'email' => $request->user()->email,
                'account_type' => $request->user()->isFirmUser() ? 'practice' : 'business',
                'firm_id' => $request->user()->firm_id,
                'tenant_id' => $request->user()->tenant_id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'We could not send the verification email. Please try again in a minute.');
        }

        return back()->with('status', 'verification-link-sent');
    }
}
