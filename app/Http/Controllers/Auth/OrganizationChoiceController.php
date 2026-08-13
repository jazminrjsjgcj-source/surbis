<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\ActiveOrganizationContext;
use App\Application\Identity\EstablishAuthenticatedContext;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Un usuario puede pertenecer a varias organizaciones (P-004) y elige en
 * cual opera. El colaborador nunca llega aqui: RF-AUT-004 le prohibe el
 * selector, y su organizacion sale del dispositivo de quiosco.
 */
final class OrganizationChoiceController extends Controller
{
    public function create(Request $request, ActiveOrganizationContext $context): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('auth.choose-organization', [
            'memberships' => $context->usableMemberships($user),
        ]);
    }

    public function store(
        Request $request,
        ActiveOrganizationContext $context,
        EstablishAuthenticatedContext $establish,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'organization' => ['required', 'string'],
        ]);

        // El identificador llega del navegador, asi que aqui no se confia en
        // el: se busca entre las membresias que ese usuario puede usar. Si no
        // esta, no existe para el. RNF-COL-001 aplicado fuera del quiosco.
        $membership = $context->usableMemberships($user)
            ->first(fn (Membership $candidate): bool => $candidate->organization->ulid === $validated['organization']);

        if ($membership === null) {
            throw ValidationException::withMessages([
                'organization' => __('auth.organization_not_available'),
            ]);
        }

        $context->remember($membership);

        return redirect()->route($establish->homeFor($membership));
    }
}
