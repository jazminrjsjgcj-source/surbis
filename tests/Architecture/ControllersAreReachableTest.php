<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Todo controlador tiene al menos una ruta.
 *
 * ESTA PRUEBA NACE DE CUATRO FALLOS REALES encontrados el mismo dia, y
 * ninguno lo detectaba nada:
 *
 *   MediaUploadController      la subida de imagenes, escrita en la Fase 5
 *   SurveyPreviewController    la vista previa, escrita en la Fase 7
 *
 * Los dos tenian dominio completo, validacion, pantalla y pruebas de sus
 * piezas. Lo que faltaba era la LINEA que los conecta, y sin ella no habia
 * forma de llegar: el boton no existia porque la ruta no existia.
 *
 * Las pruebas normales no lo ven porque comprueban lo que HAY. Una
 * funcionalidad inalcanzable no rompe nada; simplemente no esta, y eso solo
 * se descubre cuando alguien intenta usarla.
 */
final class ControllersAreReachableTest extends TestCase
{
    /**
     * Controladores que a proposito NO tienen ruta propia.
     *
     * La lista se mantiene corta y con su motivo escrito: una excepcion sin
     * explicar acaba siendo el sitio donde se esconde el siguiente olvido.
     *
     * @var list<string>
     */
    private const SIN_RUTA_PROPIA = [
        // La clase base de la que heredan todos.
        'App\Http\Controllers\Controller',

        /*
         * Se invoca desde closures —app(PlaceholderController::class)(...)—
         * para las pantallas que aun no existen, asi que el router nunca lo
         * ve como controlador de ruta.
         *
         * Cuando no queden marcadores, este controlador se borra y con el
         * esta excepcion.
         */
        'App\Http\Controllers\PlaceholderController',
    ];

    public function test_ningun_controlador_se_queda_sin_ruta(): void
    {
        $enRutas = $this->controllersWithRoutes();
        $huerfanos = [];

        foreach ($this->allControllers() as $clase) {
            if (in_array($clase, self::SIN_RUTA_PROPIA, true)) {
                continue;
            }

            if (! in_array($clase, $enRutas, true)) {
                $huerfanos[] = $clase;
            }
        }

        sort($huerfanos);

        $this->assertSame([], $huerfanos, $this->explain($huerfanos));
    }

    /**
     * Todos los controladores del proyecto, por su nombre de clase.
     *
     * Se leen del DISCO y no de una lista escrita a mano: una lista habria
     * que acordarse de ampliarla al crear un controlador nuevo, y quien
     * olvida la ruta tambien olvidaria eso.
     *
     * @return list<string>
     */
    private function allControllers(): array
    {
        $base = app_path('Http/Controllers');

        if (! is_dir($base)) {
            return [];
        }

        $clases = [];

        foreach (Finder::create()->files()->in($base)->name('*Controller.php') as $archivo) {
            /** @var SplFileInfo $archivo */
            $relativa = Str::of($archivo->getRealPath())
                ->after(app_path().DIRECTORY_SEPARATOR)
                ->replace(DIRECTORY_SEPARATOR, '\\')
                ->beforeLast('.php')
                ->toString();

            $clases[] = 'App\\'.$relativa;
        }

        return $clases;
    }

    /**
     * Los controladores que aparecen en alguna ruta registrada.
     *
     * Se pregunta al ROUTER, no se lee routes/web.php con expresiones
     * regulares: asi cuentan tambien las rutas de la API, las de consola y
     * cualquier grupo que se anada despues.
     *
     * @return list<string>
     */
    private function controllersWithRoutes(): array
    {
        $clases = [];

        foreach (Route::getRoutes() as $ruta) {
            $accion = $ruta->getAction('controller');

            if (! is_string($accion)) {
                continue;
            }

            // "App\Http\Controllers\Foo@bar" o la clase sola si es invocable.
            $clases[] = Str::before($accion, '@');
        }

        return array_values(array_unique($clases));
    }

    /** @param list<string> $huerfanos */
    private function explain(array $huerfanos): string
    {
        if ($huerfanos === []) {
            return '';
        }

        return "Controladores sin ninguna ruta —escritos pero inalcanzables—:\n  "
            .implode("\n  ", $huerfanos)
            ."\n\nO se les da una ruta, o se borran, o se anaden a SIN_RUTA_PROPIA con su motivo.";
    }
}
