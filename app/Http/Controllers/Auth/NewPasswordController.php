<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\ResetPassword;
use App\Domain\Identity\PasswordPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): InertiaResponse
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
            'action' => route('password.store'),

            /*
             * La politica viene del servidor, de la MISMA constante que aplica
             * la validacion.
             *
             * La primera version de la pantalla escribia "12" a mano en el
             * componente. Eso es justo la segunda verdad que
             * PasswordResetTest existe para impedir: el texto podria prometer
             * 8 caracteres mientras el servidor exige 12, y nadie lo
             * detectaria hasta que alguien se atascara.
             */
            'minLength' => PasswordPolicy::MIN_LENGTH,
        ]);
    }

    public function store(NewPasswordRequest $request, ResetPassword $reset): RedirectResponse
    {
        $status = $reset->execute([
            'token' => (string) $request->string('token'),
            'email' => (string) $request->string('email'),
            'password' => (string) $request->string('password'),
            'password_confirmation' => (string) $request->string('password_confirmation'),
        ]);

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            // Token caducado, ya usado o correo que no corresponde. El mensaje
            // es el mismo para los tres: distinguirlos diria si la cuenta
            // existe. RNF-AUT-007.
            throw ValidationException::withMessages([
                'email' => __('passwords.token'),
            ]);
        }

        return redirect()->route('login')->with('status', __('passwords.reset'));
    }
}
