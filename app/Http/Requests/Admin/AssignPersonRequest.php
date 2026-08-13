<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Identity\Models\Membership;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $organizationId = $this->activeMembership()?->organization_id;

        return [
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

    private function activeMembership(): ?Membership
    {
        $membership = $this->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
