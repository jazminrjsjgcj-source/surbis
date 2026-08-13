<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class SecondFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Sin `numeric`: tambien se aceptan codigos de recuperacion, que
            // llevan letras y un guion. RF-AUT-014.
            'code' => ['required', 'string', 'max:64'],
        ];
    }
}
