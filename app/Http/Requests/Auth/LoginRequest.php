<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Returns true if the credentials were valid AND the user has 2FA
     * enabled — in that case we deliberately *do not* log them in.
     * Instead we leave the controller to redirect to the challenge
     * page; only after a valid TOTP / recovery code does the user get
     * an authenticated session.
     *
     * Returns false in the simple "no 2FA" case where the user is now
     * fully logged in.
     *
     * @throws \Illuminate\Validation\ValidationException  on bad creds
     */
    public function authenticate(): bool
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');
        $remember = $this->boolean('remember');

        // Validate without logging in so we can branch on 2FA state.
        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::getProvider()->retrieveByCredentials($credentials);

        if ($user && $user->two_factor_secret && $user->two_factor_confirmed_at) {
            // Stash the pending login in the session — the challenge
            // controller picks this up. Notably we *do not* call
            // Auth::login(); the user is not authenticated until the
            // 2FA challenge succeeds.
            $this->session()->put('auth.2fa.pending_user_id', $user->getAuthIdentifier());
            $this->session()->put('auth.2fa.remember', $remember);
            RateLimiter::clear($this->throttleKey());
            return true;
        }

        // No 2FA — log them in immediately as before.
        Auth::login($user, $remember);
        RateLimiter::clear($this->throttleKey());
        return false;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
