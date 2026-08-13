<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Models\Membership;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteMemberRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',

                /*
                 * La unicidad NO se comprueba aqui.
                 *
                 * Rule::unique sobre memberships.user_id compararia el correo
                 * contra una columna de identificadores: no encontraria nada
                 * nunca y pasaria siempre. Una regla que no puede fallar es
                 * peor que ninguna, porque parece que protege.
                 *
                 * La comprobacion real esta en withValidator(), que busca al
                 * usuario por correo antes de mirar sus membresias.
                 * RNF-AO-COL-003 y P-004.
                 */
            ],

            'role' => ['required', Rule::enum(MembershipRole::class)],

            'branch_id' => [
                'nullable',
                // La sucursal tiene que ser de ESTA organizacion. Sin el
                // where, un id de otra organizacion pasaria la validacion.
                Rule::exists('branches', 'id')->where('organization_id', $organizationId),
            ],

            'area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }

    /**
     * La regla unique sobre user_id no puede resolverse sola: hay que buscar
     * el usuario por correo primero. Se hace aqui, despues de validar el
     * formato, para no consultar con basura.
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

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('interface.people.name'),
            'email' => __('interface.people.email'),
            'role' => __('interface.people.role'),
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
