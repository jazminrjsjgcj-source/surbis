<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Las fuentes compiladas tienen que llegar al navegador.
 *
 * FontsTest comprueba que se compilan las correctas. Esta comprueba la otra
 * mitad, que es la que fallaba: se compilaban y ninguna pagina las pedia.
 *
 * Las dos juntas cierran el hueco. Por separado, cada una pasa mientras la
 * otra esta rota.
 */
final class FontsAreLoadedTest extends TestCase
{
    public function test_la_pagina_declara_las_fuentes_del_sistema(): void
    {
        $respuesta = $this->get('/login')->assertOk();

        // @font-face embebido: es lo que emite Vite::fonts().
        $respuesta->assertSee('@font-face', false);

        foreach (['Poppins', 'Inter'] as $familia) {
            $respuesta->assertSee($familia, false);
        }
    }

    public function test_las_declaraciones_van_antes_de_la_hoja_de_estilos(): void
    {
        /*
         * El orden importa: si los @font-face llegaran despues de la hoja que
         * los usa, el navegador pintaria primero con la tipografia de
         * respaldo y el texto saltaria al cargar la real.
         */
        $html = $this->get('/login')->assertOk()->getContent();

        $fontFace = strpos((string) $html, '@font-face');
        $hoja = strpos((string) $html, 'app-');

        $this->assertNotFalse($fontFace, 'La pagina no declara ninguna fuente.');
        $this->assertNotFalse($hoja, 'La pagina no carga la hoja de estilos.');
        $this->assertLessThan($hoja, $fontFace, 'Los @font-face llegan despues de la hoja que los usa.');
    }
}
