<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Failed attempts allowed per email+IP pair before the account is locked out
     * of password authentication for LOGIN_LOCKOUT_SECONDS.
     */
    private const MAX_ATTEMPTS = 5;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Stop the request before credentials are checked.
     *
     * FinancePro moves money, so unlimited online password guessing is not an
     * acceptable default. The throttle is keyed on the submitted email *and* the
     * client address: the email stops one account being sprayed, and the address
     * stops one host spraying many accounts.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => sprintf(
                'Too many login attempts. Please try again in %d seconds.',
                RateLimiter::availableIn($this->throttleKey())
            ),
        ]);
    }

    /**
     * Record a failed attempt against the throttle key.
     */
    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey());
    }

    /**
     * Clear the counter after a successful authentication.
     */
    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * The throttle bucket for this request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->string('email')).'|'.$this->ip()
        );
    }
}
