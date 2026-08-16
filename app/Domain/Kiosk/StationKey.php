<?php

declare(strict_types=1);

namespace App\Domain\Kiosk;

use Illuminate\Support\Str;

/**
 * La clave que identifica a una tableta. TASK-005 · RNF-COL-001.
 *
 * Una tableta de ventanilla NO tiene usuario: nadie escribe un correo y una
 * contrasena cada manana, y pedirselo al colaborador significaria que sus
 * credenciales quedan escritas en un dispositivo que esta a la vista del
 * publico.
 *
 * Se guarda el HASH, nunca la clave. Y como los tokens publicos, solo existe
 * en claro al generarla: si se pierde, se genera otra y la anterior deja de
 * valer.
 */
final class StationKey
{
    /**
     * Formato legible: cuatro grupos de cuatro.
     *
     * Alguien la va a teclear en una tableta, probablemente con el dedo. Un
     * token de 32 caracteres seguidos se escribe mal tres veces antes de
     * acertar; "K7M2-9XPQ-4RTV-8NWC" se lee en voz alta sin errores.
     *
     * Sin I, O, 0 ni 1: en una pantalla pequena no se distinguen.
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const GROUPS = 4;

    private const GROUP_SIZE = 4;

    public function generate(): string
    {
        $grupos = [];

        foreach (range(1, self::GROUPS) as $i) {
            $grupo = '';

            foreach (range(1, self::GROUP_SIZE) as $j) {
                /*
                 * random_int y no rand(): es criptograficamente seguro.
                 *
                 * Una clave predecible permitiria suplantar una tableta y
                 * enviar respuestas falsas atribuidas a una ventanilla real.
                 */
                $grupo .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $grupos[] = $grupo;
        }

        return implode('-', $grupos);
    }

    /**
     * SHA-256, como los tokens publicos.
     *
     * Permite buscar por hash en una consulta, cosa que bcrypt no haria
     * porque cada hash lleva su propia sal. Y una clave de 16 caracteres
     * aleatorios no necesita el coste de bcrypt: ese coste existe para
     * contrasenas humanas, que son cortas y adivinables.
     */
    public function hash(string $key): string
    {
        return hash('sha256', $this->normalize($key));
    }

    public function matches(string $key, string $hash): bool
    {
        // Tiempo constante: comparar con === permitiria deducir el hash
        // midiendo cuanto tarda en fallar.
        return hash_equals($hash, $this->hash($key));
    }

    /**
     * Mayusculas y sin guiones ni espacios.
     *
     * Quien la teclee puede escribirla en minusculas, sin guiones o con
     * espacios de mas. Todas esas son la misma clave, y rechazarlas por el
     * formato seria hacer perder el tiempo a alguien que la escribio bien.
     */
    private function normalize(string $key): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $key) ?? '');
    }
}
