<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Surveys\Enums\OptionDisplay;
use App\Domain\Surveys\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BuilderStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            /*
             * lock_version es obligatorio y sin valor por defecto.
             *
             * Si se admitiera ausente y se tratara como 0, un cliente que lo
             * olvidara sobrescribiria el trabajo de otro sin enterarse. Un
             * guardado sin numero de version no es un guardado descuidado: es
             * uno que se salta la proteccion.
             */
            'lock_version' => ['required', 'integer', 'min:0'],

            'questions' => ['present', 'array', 'max:200'],
            'questions.*.ulid' => ['nullable', 'string', 'size:26'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            /*
             * El texto puede estar VACIO en el borrador.
             *
             * Una pregunta recien anadida no tiene texto: es su estado normal
             * durante los segundos que se tarda en escribirlo. Exigirlo aqui
             * hacia que el autoguardado devolviera 422 en bucle —208
             * peticiones en una prueba— y que cada intento moviera
             * lock_version, acabando en un conflicto falso contra uno mismo.
             *
             * Lo que NO puede publicarse sin texto ya lo impide
             * PublicationChecklist. La exigencia no desaparece: se mueve al
             * momento en que tiene sentido.
             */
            'questions.*.text' => ['present', 'nullable', 'string', 'max:1000'],
            'questions.*.help' => ['nullable', 'string', 'max:1000'],
            'questions.*.is_required' => ['required', 'boolean'],
            'questions.*.limits' => ['present', 'array'],

            'questions.*.options' => ['present', 'array', 'max:50'],
            'questions.*.options.*.ulid' => ['nullable', 'string', 'size:26'],

            // La etiqueta es obligatoria SIEMPRE, tambien cuando la opcion se
            // muestra solo como imagen: es el nombre accesible de
            // RF-AO-BLD-005.
            // Igual que el texto: una opcion recien anadida esta vacia
            // mientras se escribe. PublicationChecklist lo exige al publicar.
            'questions.*.options.*.label' => ['present', 'string', 'max:255'],

            'questions.*.options.*.value' => ['present', 'string', 'max:255'],
            'questions.*.options.*.score' => ['nullable', 'integer', 'between:-32768,32767'],
            'questions.*.options.*.display' => ['required', Rule::enum(OptionDisplay::class)],
            'questions.*.options.*.appearance' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            // Los mensajes de 'required' se retiran con sus reglas: el
            // borrador admite campos vacios. Un mensaje para una regla que ya
            // no existe es codigo muerto que confunde al leerlo.
            'questions.*.text.max' => __('validation.question_text_max'),
        ];
    }
}
