<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * RA-001: solo /platform/*, y sin acceso automatico al contenido de ninguna
 * organizacion. Ese acceso pasa por support_grants y llega en su tarea.
 */
final class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->isPlatformAdmin()) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}
