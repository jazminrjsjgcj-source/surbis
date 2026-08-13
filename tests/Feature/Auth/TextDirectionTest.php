<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Shared\Localization\TextDirection;
use Tests\TestCase;

/**
 * Criterio que quedo pendiente al cerrar la Fase 0: cambiar el idioma debe
 * invertir la interfaz sin romperla. Ahora existe la primera pantalla contra
 * la que comprobarlo.
 *
 * ANEXO 1 secciones 50 y 96.
 */
final class TextDirectionTest extends TestCase
{
    public function test_el_espanol_se_escribe_de_izquierda_a_derecha(): void
    {
        $this->assertSame('ltr', TextDirection::forLocale('es'));
        $this->assertSame('ltr', TextDirection::forLocale('es_MX'));
    }

    public function test_el_arabe_se_escribe_de_derecha_a_izquierda(): void
    {
        $this->assertSame('rtl', TextDirection::forLocale('ar'));
        $this->assertSame('rtl', TextDirection::forLocale('ar-EG'));
    }

    public function test_la_pantalla_de_acceso_declara_su_direccion(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('lang="es"', false);
    }

    public function test_cambiar_a_arabe_invierte_la_pantalla_de_acceso(): void
    {
        app()->setLocale('ar');

        $this->get('/login')
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }
}
