<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(
        PasswordResetLinkRequest $request,
        SendPasswordResetLink $send,
    ): RedirectResponse {
        $request->ensureIsNotRateLimited();
        $request->registerAttempt();

        $send->execute((string) $request->string('email'));

        // Siempre el mismo mensaje. Si aqui se distinguiera entre "enviado" y
        // "ese correo no existe", la pantalla se convertiria en un
        // comprobador de cuentas registradas. RF-AUT-009 y RNF-AUT-007.
        return back()->with('status', __('passwords.sent_if_registered'));
    }
}
