<?php

declare(strict_types=1);

namespace App\Application\Identity\Exceptions;

use RuntimeException;

/**
 * Un unico motivo por el que no se concede la sesion, con cinco razones.
 *
 * Cinco clases de excepcion casi identicas no aportarian nada: lo que cambia
 * entre ellas es el mensaje y si hay que penalizar el intento. Eso son dos
 * datos, no cinco jerarquias.
 *
 * RF-AUT-002 y RF-AUT-005.
 */
final class AuthenticationDenied extends RuntimeException
{
    private function __construct(
        public readonly string $translationKey,
        public readonly bool $isCredentialFailure,
    ) {
        parent::__construct($translationKey);
    }

    public static function invalidCredentials(): self
    {
        return new self('auth.failed', true);
    }

    public static function userSuspended(): self
    {
        return new self('auth.user_suspended', false);
    }

    public static function organizationSuspended(): self
    {
        return new self('auth.organization_suspended', false);
    }

    public static function membershipSuspended(): self
    {
        return new self('auth.membership_suspended', false);
    }

    public static function withoutMembership(): self
    {
        return new self('auth.without_membership', false);
    }

    public function userMessage(): string
    {
        return __($this->translationKey);
    }
}
