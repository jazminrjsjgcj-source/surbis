<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\StaffMember;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StaffMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $organizationId = $this->activeMembership()?->organization_id;
        $staff = $this->route('staffMember');

        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],

            'employee_code' => [
                'nullable',
                'string',
                'max:32',

                // Unico por organizacion y solo cuando existe: el indice de
                // la base es parcial, y la regla tiene que decir lo mismo.
                Rule::unique('staff_members', 'employee_code')
                    ->where('organization_id', $organizationId)
                    ->ignore($staff instanceof StaffMember ? $staff->id : null),
            ],

            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where('organization_id', $organizationId),
            ],

            'area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'first_name' => __('interface.people.first_name'),
            'last_name' => __('interface.people.last_name'),
            'employee_code' => __('interface.people.employee_code'),
            'branch_id' => __('interface.people.branch'),
            'area_id' => __('interface.people.area'),
        ];
    }

    private function activeMembership(): ?Membership
    {
        $membership = $this->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
