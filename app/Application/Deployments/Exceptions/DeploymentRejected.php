<?php

declare(strict_types=1);

namespace App\Application\Deployments\Exceptions;

use RuntimeException;

/**
 * Un deployment que no se puede crear o cambiar.
 *
 * Lleva una CLAVE y no un texto: la pantalla la traduce y la API la puede
 * interpretar. Un mensaje en espanol dentro del dominio obliga a que todo lo
 * que lo consuma hable espanol.
 */
final class DeploymentRejected extends RuntimeException
{
    /** @param array<string, mixed> $replacements */
    private function __construct(
        public readonly string $key,
        public readonly array $replacements = [],
    ) {
        parent::__construct("deployment.{$key}");
    }

    /** RF-AO-DEP-003: solo se despliegan versiones publicadas. */
    public static function versionNotPublished(): self
    {
        return new self('version_not_published');
    }

    /** El quiosco exige dispositivo. Decision del area usuaria. */
    public static function kioskNeedsDevice(): self
    {
        return new self('kiosk_needs_device');
    }

    /** RNF-GEN-005: nunca se mezclan entidades de organizaciones distintas. */
    public static function foreignEntity(string $entity): self
    {
        return new self('foreign_entity', ['entity' => $entity]);
    }

    /** Un deployment con respuestas no se borra ni se reasigna en sitio. */
    public static function hasHistory(int $responses): self
    {
        return new self('has_history', ['responses' => $responses]);
    }

    /** Un deployment cerrado no se reabre: se crea uno nuevo. */
    public static function alreadyClosed(): self
    {
        return new self('already_closed');
    }

    /** RNF-AO-DEP-001. */
    public static function datesOutOfOrder(): self
    {
        return new self('dates_out_of_order');
    }

    /** El alcance declarado no coincide con lo que se paso. */
    public static function scopeMismatch(string $scope): self
    {
        return new self('scope_mismatch', ['scope' => $scope]);
    }
}
