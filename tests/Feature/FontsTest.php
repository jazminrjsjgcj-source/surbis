<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Las fuentes que se compilan tienen que ser las que el sistema declara.
 *
 * Esta prueba existe porque el defecto que la motivo era completamente
 * invisible: la pagina cargaba, se veia bien, y descargaba 116 kB de una
 * tipografia que nadie usaba mientras las declaradas no llegaban nunca. No
 * daba error, no rompia nada, y solo se detecto mirando el listado de
 * archivos compilados.
 */
final class FontsTest extends TestCase
{
    public function test_la_configuracion_declara_las_familias_del_sistema(): void
    {
        $config = file_get_contents(base_path('vite.config.js'));

        foreach (['Poppins', 'Inter', 'Pacifico', 'JetBrains Mono'] as $familia) {
            $this->assertStringContainsString(
                "bunny('{$familia}'",
                (string) $config,
                "vite.config.js no declara {$familia}, que app.css si usa.",
            );
        }
    }

    public function test_no_se_compila_ninguna_fuente_que_el_sistema_no_use(): void
    {
        // Instrument Sans era el valor por defecto de Laravel 13. Si vuelve,
        // vuelve el peso muerto.
        $config = (string) file_get_contents(base_path('vite.config.js'));

        $this->assertStringNotContainsString('Instrument Sans', $config);
    }

    public function test_cada_familia_declarada_en_el_css_esta_en_la_configuracion(): void
    {
        /*
         * La comprobacion que de verdad importa: no que haya cuatro nombres
         * escritos, sino que los tokens de app.css y la configuracion de
         * compilacion digan lo mismo. Si alguien anade una familia al tema y
         * se olvida del build, esta prueba lo dice.
         */
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $config = (string) file_get_contents(base_path('vite.config.js'));

        preg_match_all("/--font-[a-z]+:\s*'([^']+)'/", $css, $coincidencias);

        $this->assertNotEmpty($coincidencias[1], 'No se encontro ninguna familia en los tokens.');

        foreach ($coincidencias[1] as $familia) {
            $this->assertStringContainsString(
                $familia,
                $config,
                "El token --font declara '{$familia}' y vite.config.js no la compila.",
            );
        }
    }
}
