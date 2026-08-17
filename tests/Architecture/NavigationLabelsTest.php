<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Domain\Identity\Models\Membership;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Cada entrada del menu tiene su etiqueta traducida.
 *
 * ESTA PRUEBA NACE DE UN FALLO REAL. El menu mostro
 * "interface.nav.deployments" escrito en crudo durante CUATRO fases, y
 * TranslationKeysTest no lo cazo.
 *
 * Por que se escapa: AdminShell pide las etiquetas con una plantilla —
 * t(`interface.nav.${item.key}`)— y esas claves se registran como PREFIJO y
 * quedan exentas de la comprobacion. No se puede saber leyendo el codigo que
 * `item.key` valdra "deployments": el valor sale de los datos.
 *
 * Aqui se cierra el hueco por el otro lado: se leen las entradas que el
 * servidor manda de verdad y se comprueba que cada una tenga texto.
 */
final class NavigationLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_entrada_del_menu_tiene_etiqueta(): void
    {
        $membership = Membership::factory()->create();

        $this->actingAs($membership->user);

        /*
         * Se llama al middleware de verdad, no se copia la lista.
         *
         * Copiarla aqui crearia una segunda verdad: anadir una entrada al
         * menu y olvidarla en la copia dejaria la prueba en verde con el
         * fallo dentro.
         */
        $request = Request::create(route('admin.dashboard'));
        $request->setUserResolver(fn () => $membership->user);

        $compartido = app(HandleInertiaRequests::class)->share($request);

        /** @var list<array{key: string, url: string}> $nav */
        $nav = $compartido['nav'];

        $this->assertNotEmpty($nav, 'El menu no deberia estar vacio para un usuario con sesion.');

        $sinEtiqueta = [];

        foreach ($nav as $entrada) {
            $clave = "interface.nav.{$entrada['key']}";

            /*
             * __() devuelve la CLAVE cuando no encuentra traduccion.
             *
             * Es justo lo que se veia en pantalla: "interface.nav.responses"
             * donde tenia que poner "Respuestas".
             */
            if (__($clave) === $clave) {
                $sinEtiqueta[] = $clave;
            }
        }

        $this->assertSame([], $sinEtiqueta, $this->explain($sinEtiqueta));
    }

    /** @param list<string> $faltan */
    private function explain(array $faltan): string
    {
        if ($faltan === []) {
            return '';
        }

        return "Entradas del menu sin etiqueta en lang/es/interface.php:\n  ".implode("\n  ", $faltan);
    }
}
