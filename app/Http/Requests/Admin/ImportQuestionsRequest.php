<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ImportQuestionsRequest extends FormRequest
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
             * Un tope generoso pero real.
             *
             * 20.000 caracteres son unas 200 preguntas con sus escalas. El
             * limite no esta por gusto: PHP descarta campos de mas sin decir
             * nada (ANEXO 1 seccion 55), y un texto sin tope acabaria
             * llegando truncado en silencio.
             */
            'text' => ['required', 'string', 'max:20000'],
            'mode' => ['required', 'in:append,replace'],
        ];
    }
}
