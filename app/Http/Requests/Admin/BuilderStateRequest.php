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
            'questions.*.text' => ['required', 'string', 'max:1000'],
            'questions.*.help' => ['nullable', 'string', 'max:1000'],
            'questions.*.is_required' => ['required', 'boolean'],
            'questions.*.limits' => ['present', 'array'],

            /*
             * La condicion, si la hay. RF-AO-BLD-007.
             *
             * nullable en el nivel superior y required dentro: o no hay
             * condicion, o esta completa. Una condicion a medias —con
             * pregunta origen pero sin opcion— se guardaria y no haria nada.
             */
            'questions.*.condition' => ['nullable', 'array'],
            'questions.*.condition.depends_on_ulid' => ['required_with:questions.*.condition', 'string', 'size:26'],
            'questions.*.condition.option_ulid' => ['required_with:questions.*.condition', 'string', 'size:26'],

            'questions.*.options' => ['present', 'array', 'max:50'],
            'questions.*.options.*.ulid' => ['nullable', 'string', 'size:26'],

            // La etiqueta es obligatoria SIEMPRE, tambien cuando la opcion se
            // muestra solo como imagen: es el nombre accesible de
            // RF-AO-BLD-005.
            'questions.*.options.*.label' => ['required', 'string', 'max:255'],

            'questions.*.options.*.value' => ['required', 'string', 'max:255'],
            'questions.*.options.*.score' => ['nullable', 'integer', 'between:-32768,32767'],
            'questions.*.options.*.display' => ['required', Rule::enum(OptionDisplay::class)],
            'questions.*.options.*.appearance' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'questions.*.text.required' => __('validation.question_text_required'),
            'questions.*.options.*.label.required' => __('validation.option_label_required'),
        ];
    }
}
