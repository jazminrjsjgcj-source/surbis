<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PasswordResetLinkRequest extends FormRequest
{
    /**
     * RNF-AUT-006 pide limite por cuenta e IP. El broker de Laravel ya trae
     * uno propio, pero solo por cuenta: sin este, alguien podria recorrer una
     * lista de correos desde la misma direccion sin freno.
     */
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }

    public function throttleKey(): string
    {
        return Str::transliterate('password-reset|'.Str::lower((string) $this->string('email')).'|'.$this->ip());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    public function registerAttempt(): void
    {
        RateLimiter::hit($this->throttleKey());
    }
}
