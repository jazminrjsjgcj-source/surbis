<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Application\Identity\ChangePassword;
use App\Application\Identity\ManageSecondFactor;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\SecondFactorAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

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
    public function show(Request $request, SecondFactorAvailability $availability): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Account/Security', [
            'mfaEnabled' => $user->hasMfaEnabled(),

            /*
             * Los codigos vienen de un flash de sesion y se muestran UNA sola
             * vez: en la base solo queda su hash.
             *
             * Van como prop diferida —el closure— para que Inertia NO los
             * incluya al recargar parcialmente ni los deje en el estado que
             * guarda el navegador. Sin esto, un "atras" podria volver a
             * ensenar unos codigos que ya se consideraban entregados.
             */
            'recoveryCodes' => fn () => $request->session()->get('recovery_codes'),

            /*
             * getAttributeValue y no la propiedad directa.
             *
             * Acceder con -> lanza cuando el modelo se cargo sin esa columna
             * —un User creado por factory, o una consulta con select
             * parcial—. getAttributeValue devuelve null, que es lo correcto:
             * si no se sabe, no se avisa.
             *
             * Si la contrasena la puso quien creo la cuenta.
             *
             * Se avisa, no se obliga: el cambio es voluntario (decision del
             * area usuaria). Pero mientras no ocurra, esa contrasena la
             * conocen dos personas.
             */
            'passwordSetByOther' => ($request->user()?->getAttributes()['password_set_by_other_at'] ?? null) !== null,

            'passwordUrl' => route('account.password'),
            'available' => $availability->isAvailable(),
            'unavailableReason' => $availability->unavailableReason(),

            'enableUrl' => route('account.security.enable'),
            'disableUrl' => route('account.security.disable'),
            'codesUrl' => route('account.security.codes'),
            'backUrl' => route('admin.dashboard'),
        ]);
    }

    public function updatePassword(
        ChangePasswordRequest $request,
        ChangePassword $change,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $change->execute($user, $request->string('password')->toString());

        /*
         * La sesion se renueva tras el cambio.
         *
         * Sin esto, el identificador de sesion sigue siendo el mismo de
         * antes: si alguien lo tenia, la contrasena nueva no le habria
         * quitado nada.
         */
        $request->session()->regenerate();

        return back()->with('status', __('interface.security.password_changed'));
    }

    public function enable(
        Request $request,
        ManageSecondFactor $manage,
        SecondFactorAvailability $availability,
    ): RedirectResponse {
        // Que el boton no aparezca no impide enviar la peticion a mano, y el
        // resultado seria una cuenta bloqueada de verdad.
        if (! $availability->isAvailable()) {
            return back()->withErrors(['mfa' => __('interface.security.unavailable_mail')]);
        }

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
