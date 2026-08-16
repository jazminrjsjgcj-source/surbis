<?php

declare(strict_types=1);

namespace App\Domain\Responses;

use Illuminate\Support\Str;

/**
 * Indice ciego: buscar un dato cifrado sin descifrarlo.
 *
 * Decision del area usuaria, 17 ago 2026: los datos identificativos se cifran
 * con indices ciegos para busqueda exacta.
 *
 * EL PROBLEMA QUE RESUELVE. Laravel cifra con una sal distinta cada vez, asi
 * que dos correos iguales producen dos textos cifrados distintos. Eso es
 * bueno —nadie deduce nada comparando filas— pero hace imposible
 * `where('email', ...)`: habria que descifrar la tabla entera.
 *
 * COMO. Junto al valor cifrado se guarda un HMAC del valor normalizado. Dos
 * correos iguales dan el mismo HMAC, asi que se pueden buscar; y del HMAC no
 * se saca el correo, asi que quien acceda a la base no lee nada.
 *
 * LO QUE NO RESUELVE, y hay que saberlo: permite busqueda EXACTA, no parcial.
 * No se puede buscar "todos los correos de gmail". Si algun dia hace falta,
 * la respuesta no es quitar el cifrado sino anadir un indice ciego mas sobre
 * el dominio.
 */
final class BlindIndex
{
    /**
     * El HMAC de un valor, normalizado.
     *
     * Normalizar importa: "Ana@Example.com " y "ana@example.com" son la misma
     * persona, y sin bajar a minusculas y recortar espacios producirian
     * indices distintos. Entonces buscar por correo fallaria justo cuando
     * alguien lo escribio con una mayuscula.
     */
    public function of(string $value): string
    {
        $normalizado = Str::lower(trim($value));

        /*
         * hash_hmac con la clave de la aplicacion, NO un hash a secas.
         *
         * Un sha256 sin clave se puede atacar con un diccionario: quien
         * robara la base podria calcular el hash de un millon de correos y
         * comparar. Con HMAC hace falta ademas la clave, que no esta en la
         * base.
         */
        return hash_hmac('sha256', $normalizado, $this->key());
    }

    /** Vacio no produce indice: null significa "no hay dato que buscar". */
    public function ofNullable(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $this->of($value);
    }

    private function key(): string
    {
        $key = (string) config('app.key');

        return Str::startsWith($key, 'base64:')
            ? (string) base64_decode(Str::after($key, 'base64:'), true)
            : $key;
    }
}
