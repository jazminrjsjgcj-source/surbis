<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(DeploymentChannel::values())],
            'scope' => ['required', Rule::in(DeploymentScope::values())],

            /*
             * Los alcances viajan por ULID, no por id.
             *
             * Un id secuencial revela cuantas sucursales hay en el sistema, y
             * ademas permite probar con el siguiente. Que el ULID pertenezca
             * a ESTA organizacion lo comprueba DeploymentGuard: aqui solo se
             * valida la forma.
             */
            'branch_ulid' => ['nullable', 'string', 'size:26'],
            'area_ulid' => ['nullable', 'string', 'size:26'],
            'device_ulid' => ['nullable', 'string', 'size:26'],

            // RNF-AO-DEP-001. El orden entre ellas lo comprueba el guardian,
            // que puede decirlo con un mensaje entendible.
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ];
    }
}
