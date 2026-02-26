<?php

namespace App\Http\Requests\Auth;

use App\Services\AccountLockoutService;
use App\Services\LoginLoggerService;
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
     * @return \App\Models\User
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate()
    {
        $this->ensureIsNotRateLimited();
        $this->ensureAccountNotLocked();

        $credentials = [
            'email' => $this['email'],
            'password' => $this['password'],
        ];

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey());

            // Log failed login attempt and handle account lockout
            app(LoginLoggerService::class)->logFailedLogin($this['email'], $this);

            if ($this['email']) {
                $user = \App\Models\User::where('email', $this['email'])->first();
                if ($user) {
                    app(AccountLockoutService::class)->handleFailedLoginAttempt($user);
                }
            }

            $exception = ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
            $exception->redirectTo = route('login');
            throw $exception;
        }

        // Get the user instance
        $user = Auth::getProvider()->retrieveByCredentials($credentials);

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * Ensure the account is not locked.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureAccountNotLocked(): void
    {
        if (app(AccountLockoutService::class)->isAccountLocked($this['email'])) {
            // Get user to check lock details
            $user = \App\Models\User::where('email', $this['email'])->first();

            if ($user && $user->isAccountLocked()) {
                $timeRemaining = $user->getLockTimeRemaining();

                $exception = ValidationException::withMessages([
                    'email' => $timeRemaining
                        ? __('auth.account_locked_with_time', ['time' => $timeRemaining])
                        : __('auth.account_locked'),
                ]);
                $exception->redirectTo = route('login');
                throw $exception;
            }
        }
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

        $exception = ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
        $exception->redirectTo = route('login');
        throw $exception;
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        return Str::transliterate(Str::lower($this['email']).'|'.$ip);
    }
}
