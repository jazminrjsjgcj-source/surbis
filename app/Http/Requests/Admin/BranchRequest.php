<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Branch;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorizacion la hace la Policy en el controlador. Aqui solo se
        // valida la forma de los datos.
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $membership = $this->activeMembership();
        $branch = $this->route('branch');

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9._-]+$/',

                /*
                 * Unico POR ORGANIZACION, no globalmente. RNF-AO-BRA-002.
                 *
                 * Sin el where, dos ayuntamientos distintos no podrian tener
                 * los dos una sucursal "CENTRO", y el segundo veria un error
                 * que no puede entender ni resolver.
                 */
                Rule::unique('branches', 'code')
                    ->where('organization_id', $membership?->organization_id)
                    ->ignore($branch instanceof Branch ? $branch->id : null),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => __('validation.branch_code_format'),
            'code.unique' => __('validation.branch_code_unique'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('interface.branches.name'),
            'code' => __('interface.branches.code'),
        ];
    }

    private function activeMembership(): ?Membership
    {
        $membership = $this->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
