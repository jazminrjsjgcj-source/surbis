<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Application\Identity\ManageSecondFactor;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Seguridad de la cuenta.
 *
 * Esta pantalla no esta en REQ. Se acordo con el area usuaria el 13 ago 2026
 * (P-011) porque sin ella `mfa_confirmed_at` seria null en todos los usuarios
 * para siempre y la pantalla de verificacion, codigo inalcanzable.
 *
 * RF-AO-SET-005 hablara de gestionar el MFA de la ORGANIZACION, que es otra
 * cosa: politica para todos sus miembros. Cuando llegue, enlazara a esta en
 * lugar de duplicarla.
 */
final class SecurityController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('account.security', [
            'user' => $user,
            'recoveryCodes' => $request->session()->get('recovery_codes'),
        ]);
    }

    public function enable(Request $request, ManageSecondFactor $manage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasMfaEnabled()) {
            return back();
        }

        $codes = $manage->enable($user);

        // Los codigos viajan una sola vez, en un flash de sesion. En la base
        // solo queda su hash, asi que si el usuario cierra esta pantalla sin
        // guardarlos, nadie puede volver a mostrarselos: hay que regenerarlos.
        return back()->with([
            'status' => __('interface.security.enabled'),
            'recovery_codes' => $codes,
        ]);
    }

    public function disable(Request $request, ManageSecondFactor $manage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $manage->disable($user);

        return back()->with('status', __('interface.security.disabled'));
    }

    public function regenerate(Request $request, ManageSecondFactor $manage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasMfaEnabled()) {
            return back();
        }

        return back()->with([
            'status' => __('interface.security.codes_regenerated'),
            'recovery_codes' => $manage->regenerateRecoveryCodes($user),
        ]);
    }
}
