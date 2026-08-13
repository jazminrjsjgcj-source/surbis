<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Models\Membership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * RA-002 y RA-003: el administrador solo usa /admin, el colaborador solo el
 * quiosco. RA-005 recuerda que ocultar un boton no es autorizar; esto es lo
 * que autoriza.
 */
final class EnsureMembershipRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $membership = $request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        if (! $membership instanceof Membership) {
            return redirect()->route('login');
        }

        if ($membership->role !== MembershipRole::from($role)) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}
