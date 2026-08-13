<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\AuthenticateUser;
use App\Application\Identity\EstablishAuthenticatedContext;
use App\Application\Identity\Exceptions\AuthenticationDenied;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(
        LoginRequest $request,
        AuthenticateUser $authenticate,
        EstablishAuthenticatedContext $establish,
    ): RedirectResponse {
        $request->ensureIsNotRateLimited();

        try {
            $user = $authenticate->execute(
                (string) $request->string('email'),
                (string) $request->string('password'),
                $request->boolean('remember'),
            );
        } catch (AuthenticationDenied $denied) {
            // Solo penaliza el fallo de credenciales. Una cuenta suspendida
            // que reintenta no es un ataque, y bloquearla anadiria una
            // segunda razon invisible al mismo error.
            if ($denied->isCredentialFailure) {
                $request->registerFailedAttempt();
            }

            throw ValidationException::withMessages([
                'email' => $denied->userMessage(),
            ]);
        }

        $request->clearFailedAttempts();

        // Renovar el identificador de sesion tras autenticar cierra la
        // fijacion de sesion.
        $request->session()->regenerate();

        return redirect()->intended(route($establish->execute($user)));
    }

    public function destroy(Request $request, AuthFactory $auth): RedirectResponse
    {
        $auth->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
