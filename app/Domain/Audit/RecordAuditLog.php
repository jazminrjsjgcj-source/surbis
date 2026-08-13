<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Escribe una entrada de auditoria. RF-GEN-005.
 *
 * Existe como clase y no como llamadas sueltas a AuditLog::create porque el
 * contexto —quien, desde donde, con que organizacion activa— se resuelve
 * igual en todas partes, y porque el dia que haya que anadir un campo se
 * anade una vez.
 */
final class RecordAuditLog
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, scalar|null>  $context  Contexto tecnico.
     *                                               NUNCA datos sensibles:
     *                                               ni contrasenas, ni
     *                                               codigos, ni identidades
     *                                               de encuestados.
     *                                               RNF-GEN-014.
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $context = [],
        ?User $actor = null,
    ): AuditLog {
        // El actor se puede pasar explicitamente porque hay acciones que
        // ocurren SIN sesion: la verificacion del segundo factor pasa entre
        // la contrasena correcta y la sesion definitiva, y ahi
        // $request->user() es null. Sin este parametro, esos registros
        // saldrian sin autor y la auditoria de RF-AUT-016 no diria quien
        // hizo que.
        $user = $actor ?? $this->request->user();

        return AuditLog::query()->create([
            'organization_id' => $this->activeOrganizationId(),
            'user_id' => $user instanceof User ? $user->id : null,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'context' => $context === [] ? null : $context,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
        ]);
    }

    /**
     * La organizacion activa la resuelve el middleware y la deja en la
     * peticion. Aqui se lee de ahi y no del navegador. RF-GEN-001.
     *
     * Es null cuando la accion no pertenece a ninguna organizacion: las del
     * administrador de plataforma, y las de la propia cuenta como activar el
     * segundo factor.
     */
    private function activeOrganizationId(): ?int
    {
        $membership = $this->request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership instanceof Membership ? $membership->organization_id : null;
    }
}
