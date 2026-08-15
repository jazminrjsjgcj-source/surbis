<?php

declare(strict_types=1);

namespace App\Application\Deployments;

use Illuminate\Support\Str;

/**
 * El token de un enlace publico. RNF-AO-DEP-002.
 *
 * Se guarda el HASH, nunca el token. Si la base se filtra, los enlaces ya
 * publicados siguen sin poder deducirse: para entrar hay que tener el token,
 * y del hash no se saca.
 *
 * Eso tiene un coste que hay que asumir: el token solo existe en claro al
 * crearlo. Si alguien pierde el enlace, no se puede recuperar — hay que
 * regenerarlo, y el anterior deja de valer (RF-AO-DEP-010).
 */
final class PublicToken
{
    /**
     * 32 caracteres del alfabeto de Laravel.
     *
     * Str::random usa random_bytes por debajo, que es criptograficamente
     * seguro. NO vale rand() ni uniqid(): son predecibles, y un token
     * predecible permite entrar a encuestas ajenas probando.
     */
    private const LENGTH = 32;

    public function generate(): string
    {
        return Str::random(self::LENGTH);
    }

    /**
     * SHA-256 y no bcrypt.
     *
     * Un token de 32 caracteres aleatorios no necesita el coste de bcrypt:
     * ese coste existe para contrasenas, que son cortas y adivinables. Aqui
     * lo que hace falta es poder buscar por hash en una consulta, y bcrypt no
     * lo permite porque cada hash lleva su propia sal.
     */
    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function matches(string $token, string $hash): bool
    {
        // hash_equals y no ===: comparar en tiempo constante evita deducir el
        // hash midiendo cuanto tarda la comparacion en fallar.
        return hash_equals($hash, $this->hash($token));
    }
}
