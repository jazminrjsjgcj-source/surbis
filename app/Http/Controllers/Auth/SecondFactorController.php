<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\EstablishAuthenticatedContext;
use App\Application\Identity\PendingSecondFactor;
use App\Application\Identity\SendSecondFactorCode;
use App\Application\Identity\VerifySecondFactor;
use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SecondFactorRequest;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SecondFactorController extends Controller
{
    /** Un envio por minuto. Sin esto, el boton de reenviar inunda el correo. */
    private const RESEND_PER_MINUTE = 1;

    public function create(Request $request, PendingSecondFactor $pending, SendSecondFactorCode $send): View
    {
        /** @var User $user */
        $user = $pending->user();

        // El primer codigo se envia al llegar. Si la persona vuelve atras y
        // recarga, el limite de reenvio evita que cada recarga mande otro.
        if (RateLimiter::attempt($this->resendKey($user), self::RESEND_PER_MINUTE, fn () => null, 60)) {
            $send->execute($user);
        }

        return view('auth.second-factor', [
            'email' => $user->email,
        ]);
    }

    public function store(
        SecondFactorRequest $request,
        PendingSecondFactor $pending,
        VerifySecondFactor $verify,
        EstablishAuthenticatedContext $establish,
        AuthFactory $auth,
        RecordAuditLog $audit,
    ): RedirectResponse {
        /** @var User $user */
        $user = $pending->user();

        if (! $verify->execute($user, (string) $request->string('code'))) {
            // Se registra el intento fallido, NUNCA el codigo introducido.
            // RNF-AUT-012.
            $audit->record('mfa.verification_failed', $user, actor: $user);

            throw ValidationException::withMessages([
                'code' => __('auth.second_factor.invalid'),
            ]);
        }

        $pending->markVerified($user);
        RateLimiter::clear($this->resendKey($user));

        $auth->guard()->login($user);
        $request->session()->regenerate();

        $audit->record('mfa.verified', $user, actor: $user);

        return redirect()->route($establish->execute($user));
    }

    public function resend(Request $request, PendingSecondFactor $pending, SendSecondFactorCode $send): RedirectResponse
    {
        /** @var User $user */
        $user = $pending->user();

        if (! RateLimiter::attempt($this->resendKey($user), self::RESEND_PER_MINUTE, fn () => null, 60)) {
            return back()->withErrors([
                'code' => __('auth.second_factor.resend_too_soon'),
            ]);
        }

        $send->execute($user);

        return back()->with('status', __('auth.second_factor.resent'));
    }

    /**
     * RF-AUT-015: cancelar el proceso y cerrar la sesion parcial.
     */
    public function destroy(Request $request, PendingSecondFactor $pending): RedirectResponse
    {
        $pending->forget();
        $pending->forgetVerification();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function resendKey(User $user): string
    {
        return 'second-factor-resend|'.$user->id;
    }
}
