<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\ResetPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
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
