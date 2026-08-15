<?php

declare(strict_types=1);

namespace App\Application\Deployments;

use App\Application\Deployments\Exceptions\DeploymentRejected;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Surveys\Enums\SurveyVersionStatus;
use App\Domain\Surveys\Models\SurveyVersion;
use Carbon\CarbonImmutable;

/**
 * Las reglas que abren toda escritura de deployments.
 *
 * Existe como clase y no repartida por los casos de uso porque son las MISMAS
 * cuatro reglas en crear y en reasignar, y olvidar una en un sitio permite
 * saltarsela por esa via. Ese fallo no se ve al probar: la operacion
 * funciona, guarda, y lo que queda mal es donde aplica una encuesta.
 */
final class DeploymentGuard
{
    /**
     * @param  array{branch?: ?Branch, area?: ?Area, device?: ?Device}  $targets
     *
     * @throws DeploymentRejected
     */
    public function ensureCanDeploy(
        Organization $organization,
        SurveyVersion $version,
        DeploymentChannel $channel,
        DeploymentScope $scope,
        array $targets,
        ?CarbonImmutable $startsAt,
        ?CarbonImmutable $endsAt,
    ): void {
        /*
         * RF-AO-DEP-003. Un borrador cambia cada vez que alguien escribe: si
         * se pudiera desplegar, dos personas contestarian encuestas distintas
         * creyendo que es la misma.
         */
        if ($version->status !== SurveyVersionStatus::Published) {
            throw DeploymentRejected::versionNotPublished();
        }

        // La version tiene que ser de esta organizacion. Un ULID viaja al
        // navegador y basta con enviarlo a mano para intentarlo.
        if ($version->organization_id !== $organization->id) {
            throw DeploymentRejected::foreignEntity('version');
        }

        $this->ensureScopeMatches($scope, $targets);
        $this->ensureTargetsBelongTo($organization, $targets);

        if ($channel->requiresDevice() && ($targets['device'] ?? null) === null) {
            throw DeploymentRejected::kioskNeedsDevice();
        }

        // RNF-AO-DEP-001. La base tambien lo impide; aqui se dice antes y con
        // un mensaje que se entiende.
        if ($startsAt !== null && $endsAt !== null && $startsAt->gt($endsAt)) {
            throw DeploymentRejected::datesOutOfOrder();
        }
    }

    /**
     * Un deployment con respuestas no se borra ni se reasigna en sitio.
     *
     * RF-AO-DEP-006: reasignar es CERRAR el anterior y crear otro. Cambiarle
     * el alcance dejaria respuestas ya recibidas apuntando a un sitio donde
     * nunca se dieron.
     *
     * @throws DeploymentRejected
     */
    public function ensureNoHistory(Deployment $deployment): void
    {
        $respuestas = $this->countResponses($deployment);

        if ($respuestas > 0) {
            throw DeploymentRejected::hasHistory($respuestas);
        }
    }

    /** @throws DeploymentRejected */
    public function ensureNotClosed(Deployment $deployment): void
    {
        if ($deployment->closed_at !== null) {
            throw DeploymentRejected::alreadyClosed();
        }
    }

    /**
     * Cuantas respuestas tiene.
     *
     * La tabla `responses` no existe todavia —llega en la Fase 9— asi que
     * hoy siempre devuelve cero. Se escribe aqui, en un solo sitio, para que
     * cuando exista haya que cambiar UNA linea y no buscar por el codigo
     * quien contaba respuestas.
     *
     * Devolver cero no es fingir que la regla funciona: hoy no puede haber
     * respuestas porque no hay donde guardarlas.
     */
    private function countResponses(Deployment $deployment): int
    {
        /*
         * PENDIENTE: contar de verdad cuando exista la tabla `responses`
         * (Fase 9).
         *
         *     return $deployment->responses()->count();
         *
         * Hoy devuelve cero porque no hay donde guardar respuestas, asi que
         * la regla no puede fallar. Pero un metodo que siempre devuelve cero
         * es indistinguible de uno roto, y quien lo lea en la Fase 9 puede
         * darlo por bueno.
         *
         * Por eso DeploymentGuardTest tiene una prueba que se pondra ROJA en
         * cuanto la tabla exista: es la unica forma de que nadie se olvide.
         */
        return 0;
    }

    /** @param array{branch?: ?Branch, area?: ?Area, device?: ?Device} $targets */
    private function ensureScopeMatches(DeploymentScope $scope, array $targets): void
    {
        $esperado = match ($scope) {
            DeploymentScope::Organization => null,
            DeploymentScope::Branch => 'branch',
            DeploymentScope::Area => 'area',
            DeploymentScope::Device => 'device',
        };

        foreach (['branch', 'area', 'device'] as $clave) {
            $presente = ($targets[$clave] ?? null) !== null;

            if ($presente !== ($clave === $esperado)) {
                throw DeploymentRejected::scopeMismatch($scope->value);
            }
        }
    }

    /** @param array{branch?: ?Branch, area?: ?Area, device?: ?Device} $targets */
    private function ensureTargetsBelongTo(Organization $organization, array $targets): void
    {
        foreach ($targets as $nombre => $entidad) {
            if ($entidad !== null && $entidad->organization_id !== $organization->id) {
                throw DeploymentRejected::foreignEntity($nombre);
            }
        }
    }
}
