<?php

declare(strict_types=1);

namespace App\Domain\Shared\Concerns;

use Illuminate\Support\Str;

/**
 * Identificador publico para entidades que aparecen en una URL o en una API.
 *
 * La clave primaria sigue siendo un bigint y no sale nunca al exterior. Un id
 * secuencial en una URL revela el volumen de la organizacion y permite
 * recorrer registros ajenos probando numeros.
 *
 * RNF-AO-DEP-002 y RNF-ENC-002.
 */
trait HasPublicUlid
{
    protected static function bootHasPublicUlid(): void
    {
        static::creating(function (self $model): void {
            $model->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
