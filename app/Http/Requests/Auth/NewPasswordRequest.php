<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Identity\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class NewPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordPolicy::rules()],
        ];
    }
}
