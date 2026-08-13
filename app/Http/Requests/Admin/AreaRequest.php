<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $branch = $this->route('branch');
        $area = $this->route('area');

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9._-]+$/',

                /*
                 * Unico por SUCURSAL, no por organizacion. Dos sedes distintas
                 * pueden tener las dos un area "VENTANILLA", y es lo razonable:
                 * son ventanillas distintas en sitios distintos.
                 */
                Rule::unique('areas', 'code')
                    ->where('branch_id', $branch instanceof Branch ? $branch->id : null)
                    ->ignore($area instanceof Area ? $area->id : null),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => __('validation.branch_code_format'),
            'code.unique' => __('validation.area_code_unique'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('interface.areas.name'),
            'code' => __('interface.areas.code'),
        ];
    }
}
