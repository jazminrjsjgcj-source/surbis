<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class InvalidateResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /*
             * El motivo es obligatorio y con un minimo real.
             *
             * "x" cumpliria un required a secas y no explicaria nada. Quien
             * revise esto dentro de un año necesita saber si fue una prueba,
             * un duplicado o una manipulacion.
             */
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
