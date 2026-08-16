<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ActivateBranchKiosksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            // Por ULID, no por id: un id secuencial revela cuantas versiones
            // hay y permite probar con la siguiente.
            'version' => ['required', 'string', 'size:26'],
        ];
    }
}
