<?php

declare(strict_types=1);

namespace Tests\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Toda clave que se usa existe, y toda la que existe se usa.
 *
 * Esta prueba nace de un fallo real: cinco bloques enteros de traducciones
 * desaparecieron de interface.php en sucesivas sobrescrituras, y nadie se
 * entero hasta que aparecio "interface.import.title" escrito en la pantalla.
 *
 * Noventa claves rotas. Y ninguna prueba lo veia: las de servidor comprueban
 * props, las de navegador cubren unas pocas pantallas.
 *
 * Mira el cliente Y el servidor: las traducciones se usan desde los dos
 * lados. Su primera version solo miraba resources/js y daba por muertas
 * setenta claves que PHP usa con __() y trans_choice().
 */
final class TranslationKeysTest extends TestCase
{
    public function test_ninguna_clave_usada_se_queda_sin_traduccion(): void
    {
        $disponibles = $this->availableKeys();
        $faltan = [];

        foreach ($this->usedKeys() as $clave => $archivos) {
            if (! in_array($clave, $disponibles, true)) {
                $faltan[$clave] = implode(', ', array_unique($archivos));
            }
        }

        $this->assertSame([], $faltan, $this->explain($faltan));
    }

    public function test_no_quedan_traducciones_que_nadie_use(): void
    {
        /*
         * El reves: claves que sobran.
         *
         * No rompe nada, pero acumular claves muertas hace que el archivo
         * crezca y que revisarlo cueste. Y algunas senalan que una pantalla
         * se retiro sin limpiar detras: "display_pending" era el aviso de
         * "las imagenes llegan en la Fase 5", y esa fase ya paso.
         */
        $usadas = array_keys($this->usedKeys());
        $prefijos = $this->dynamicPrefixes();

        $sobran = array_values(array_filter(
            $this->availableKeys(),
            fn (string $clave): bool => ! in_array($clave, $usadas, true)
                && ! $this->matchesPrefix($clave, $prefijos)
        ));

        $this->assertSame([], $sobran, 'Claves que no usa nadie: '.implode(', ', $sobran));
    }

    /**
     * Las rutas completas del archivo de idioma.
     *
     * Se leen del array, no con expresiones regulares sobre el texto: los
     * bloques anidados —deployments.rejected.*— necesitan la ruta entera, y
     * buscar "'title' =>" a secas da por buena una clave que esta en otro
     * bloque. Ese fallo existio y dejo pasar seis claves rotas.
     *
     * @return list<string>
     */
    private function availableKeys(): array
    {
        /** @var array<string, mixed> $valores */
        $valores = require base_path('lang/es/interface.php');

        return array_keys($this->flatten($valores, 'interface'));
    }

    /**
     * Las claves que se piden, desde el cliente y desde el servidor.
     *
     * @return array<string, list<string>>
     */
    private function usedKeys(): array
    {
        $encontradas = [];

        // Cliente: t('interface.x.y')
        foreach ($this->filesIn(resource_path('js'), ['ts', 'tsx']) as $ruta) {
            $this->collect($encontradas, $ruta, "/t\(\s*'(interface\.[\w.]+)'/");
        }

        /*
         * Servidor: __('interface.x.y') y trans_choice('interface.x.y').
         *
         * Los mensajes de exito, los de error y los de las excepciones
         * traducidas se resuelven en PHP, no en React. Olvidarlos hacia que
         * setenta claves vivas parecieran muertas.
         */
        foreach ($this->filesIn(app_path(), ['php']) as $ruta) {
            $this->collect($encontradas, $ruta, "/(?:__|trans_choice)\(\s*'(interface\.[\w.]+)'/");
        }

        return $encontradas;
    }

    /**
     * Los prefijos de claves que se arman en tiempo de ejecucion.
     *
     * t(`interface.builder.type_${question.type}`) no se puede resolver
     * leyendo el codigo: el valor sale de los datos. Igual en PHP, con
     * "interface.deployments.rejected.{$clave}".
     *
     * @return list<string>
     */
    private function dynamicPrefixes(): array
    {
        $prefijos = [];

        foreach ($this->filesIn(resource_path('js'), ['ts', 'tsx']) as $ruta) {
            preg_match_all(
                '/t\(\s*`(interface\.[\w.]*)\$\{/',
                (string) file_get_contents($ruta),
                $coincidencias
            );

            $prefijos = [...$prefijos, ...$coincidencias[1]];
        }

        foreach ($this->filesIn(app_path(), ['php']) as $ruta) {
            preg_match_all(
                '/(?:__|trans_choice)\(\s*["\'](interface\.[\w.]*)[\'"]?\s*\.|(?:__|trans_choice)\(\s*"(interface\.[\w.]*)\{/',
                (string) file_get_contents($ruta),
                $coincidencias
            );

            $prefijos = [...$prefijos, ...array_filter($coincidencias[1]), ...array_filter($coincidencias[2])];
        }

        return array_values(array_unique($prefijos));
    }

    /**
     * @param  array<string, list<string>>  $encontradas
     */
    private function collect(array &$encontradas, string $ruta, string $patron): void
    {
        preg_match_all($patron, (string) file_get_contents($ruta), $coincidencias);

        foreach ($coincidencias[1] as $clave) {
            /*
             * Una clave terminada en punto NO es una clave: es un prefijo
             * concatenado, como trans_choice('interface.x.'.$tipo).
             *
             * Contarla como clave la marca siempre como ausente, porque la
             * ruta completa se arma en tiempo de ejecucion. Las cubre
             * dynamicPrefixes().
             */
            if (str_ends_with($clave, '.')) {
                continue;
            }

            $encontradas[$clave][] = basename($ruta);
        }
    }

    /**
     * @param  list<string>  $extensiones
     * @return list<string>
     */
    private function filesIn(string $directorio, array $extensiones): array
    {
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directorio));
        $archivos = [];

        foreach ($iterador as $archivo) {
            if ($archivo->isFile() && in_array($archivo->getExtension(), $extensiones, true)) {
                $archivos[] = $archivo->getPathname();
            }
        }

        return $archivos;
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, string>
     */
    private function flatten(array $valores, string $prefijo): array
    {
        $plano = [];

        foreach ($valores as $clave => $valor) {
            $completa = "{$prefijo}.{$clave}";

            if (is_array($valor)) {
                $plano += $this->flatten($valor, $completa);

                continue;
            }

            $plano[$completa] = (string) $valor;
        }

        return $plano;
    }

    /** @param list<string> $prefijos */
    private function matchesPrefix(string $clave, array $prefijos): bool
    {
        foreach ($prefijos as $prefijo) {
            if ($prefijo !== '' && str_starts_with($clave, $prefijo)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, string> $faltan */
    private function explain(array $faltan): string
    {
        if ($faltan === []) {
            return '';
        }

        $lineas = ['Claves usadas que NO estan en lang/es/interface.php:'];

        foreach ($faltan as $clave => $archivos) {
            $lineas[] = "  {$clave}  ({$archivos})";
        }

        return implode(PHP_EOL, $lineas);
    }
}
