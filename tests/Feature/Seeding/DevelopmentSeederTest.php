<?php

declare(strict_types=1);

namespace Tests\Feature\Seeding;

use App\Domain\Organizations\Models\Branch;
use App\Domain\Surveys\Models\Survey;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Un seeder de desarrollo no suele probarse. Este si, por dos motivos:
 *
 *   1. Crea cuentas con contrasena conocida. Si algun dia corre en
 *      produccion, el dano es inmediato y silencioso.
 *   2. Si deja de funcionar, nadie se entera hasta que alguien intenta
 *      levantar el entorno y no puede entrar.
 */
final class DevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_niega_a_correr_en_produccion(): void
    {
        /*
         * Se instancia el seeder en lugar de usar $this->seed().
         *
         * El comando db:seed pide confirmacion cuando el entorno es
         * produccion —es ConfirmableTrait— y en una prueba esa pregunta
         * revienta contra el mock de la consola antes de que el seeder llegue
         * a ejecutarse. La prueba fallaba por la confirmacion, no por la
         * guarda que pretendia comprobar.
         */
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no se ejecuta en produccion');

        app(DevelopmentSeeder::class)->run();
    }

    public function test_se_niega_a_correr_sin_contrasena(): void
    {
        // Sin valor por defecto a proposito: una contrasena escrita en el
        // repositorio acaba en produccion tarde o temprano.
        config(['seeding.password' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_PASSWORD');

        app(DevelopmentSeeder::class)->run();
    }

    public function test_las_cuentas_creadas_pueden_iniciar_sesion(): void
    {
        // La prueba que impide que el seeder cree datos que no sirven para
        // entrar, que es su unico proposito.
        $this->seedWith('desarrollo-local');

        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'desarrollo-local',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_el_colaborador_llega_al_quiosco_y_no_al_panel(): void
    {
        $this->seedWith('desarrollo-local');

        $this->post('/login', [
            'email' => 'colaborador@example.test',
            'password' => 'desarrollo-local',
        ])->assertRedirect(route('kiosk.start'));

        $this->get(route('admin.branches.index'))->assertForbidden();
    }

    public function test_las_cuentas_sin_acceso_no_entran(): void
    {
        // Estan en el seeder precisamente para poder ver el rechazo.
        $this->seedWith('desarrollo-local');

        foreach (['suspendida@example.test', 'invitada@example.test'] as $correo) {
            $this->post('/login', [
                'email' => $correo,
                'password' => 'desarrollo-local',
            ])->assertSessionHasErrors('email');

            $this->assertGuest();
            $this->flushSession();
        }
    }

    public function test_hay_datos_suficientes_para_ver_la_paginacion(): void
    {
        // Si el seeder crea menos de veintiuna personas, la paginacion no
        // aparece y nadie puede revisarla.
        $this->seedWith('desarrollo-local');

        $this->assertDatabaseCount('staff_members', 20);
        $this->assertDatabaseCount('memberships', 5);
    }

    public function test_hay_una_sucursal_sin_areas_para_ver_el_estado_vacio(): void
    {
        $this->seedWith('desarrollo-local');

        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'desarrollo-local',
        ]);

        $sur = Branch::query()->where('code', 'SUR')->firstOrFail();

        $this->get(route('admin.areas.index', $sur))
            ->assertOk()
            ->assertSee(__('interface.areas.empty_title'), false);
    }

    public function test_hay_una_encuesta_con_preguntas_y_otra_vacia(): void
    {
        /*
         * Las dos hacen falta.
         *
         * Sin una con preguntas, las pruebas de navegador del constructor no
         * pueden existir y quedan en test.fixme. Sin una vacia, el estado
         * vacio —que tiene texto propio— solo se ve borrando preguntas a
         * mano, y nadie lo hace.
         */
        $this->seedWith('desarrollo-local');

        $this->assertDatabaseCount('surveys', 2);
        $this->assertDatabaseCount('survey_versions', 2);

        $conPreguntas = Survey::query()->where('name', 'Satisfaccion en ventanilla')->firstOrFail();
        $this->assertSame(4, $conPreguntas->draft->questions()->count());

        $vacia = Survey::query()->where('name', 'Encuesta sin preguntas')->firstOrFail();
        $this->assertSame(0, $vacia->draft->questions()->count());
    }

    public function test_las_preguntas_sembradas_tienen_opciones_puntuadas(): void
    {
        // Una escala sin puntos no sirve para la analitica de la Fase 12, y
        // sembrarla mal daria una impresion falsa de que el sistema funciona.
        $this->seedWith('desarrollo-local');

        $survey = Survey::query()->where('name', 'Satisfaccion en ventanilla')->firstOrFail();
        $smiley = $survey->draft->questions()->where('type', 'smiley')->firstOrFail();

        $this->assertSame(5, $smiley->options()->count());
        $this->assertSame([1, 2, 3, 4, 5], $smiley->options()->orderBy('position')->pluck('score')->all());
    }

    private function seedWith(string $password): void
    {
        config(['seeding.password' => $password]);

        app(DevelopmentSeeder::class)->run();
    }
}
