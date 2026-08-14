<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Models\Membership;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GrantAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(MembershipRole::class)],
        ];
    }

    /**
     * La unicidad se comprueba aqui y no con Rule::unique.
     *
     * Rule::unique sobre memberships.user_id compararia el correo contra una
     * columna de identificadores: no encontraria nada nunca y pasaria
     * siempre. Una regla que no puede fallar es peor que ninguna, porque
     * parece que protege. RNF-AO-COL-003.
     */
    public function withValidator(mixed $validator): void
    {
        $validator->after(function ($validator): void {
            $organizationId = $this->activeMembership()?->organization_id;
            $email = (string) $this->string('email');

            if ($email === '' || $organizationId === null) {
                return;
            }

            $yaEsMiembro = Membership::query()
                ->where('organization_id', $organizationId)
                ->whereHas('user', fn ($query) => $query->where('email', $email))
                ->exists();

            if ($yaEsMiembro) {
                $validator->errors()->add('email', __('validation.membership_duplicate'));
            }
        });
    }

    private function activeMembership(): ?Membership
    {
        $membership = $this->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership : null;
    }
}
