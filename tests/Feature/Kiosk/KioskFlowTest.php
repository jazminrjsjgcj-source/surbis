<?php

declare(strict_types=1);

namespace Tests\Feature\Kiosk;

use App\Application\Kiosk\Exceptions\StationNotReady;
use App\Application\Kiosk\LinkStation;
use App\Application\Kiosk\ManageStationKey;
use App\Application\Kiosk\OpenKioskSession;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Kiosk\Models\KioskCredential;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Organizations\Models\StaffMember;
use App\Domain\Responses\Models\Response;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Http\Middleware\ResolveKioskStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * El quiosco de punta a punta. RF-COL-001 a 013 · RNF-COL-001 y 013.
 */
final class KioskFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_clave_temporal_se_canjea_por_una_credencial(): void
    {
        [$device, $clave] = $this->estacion();

        $this->post(route('kiosk.link.store'), ['key' => $clave])
            ->assertRedirect(route('kiosk.welcome'))
            ->assertCookie(ResolveKioskStation::COOKIE);

        $this->assertSame(1, KioskCredential::query()->where('device_id', $device->id)->count());
    }

    public function test_la_clave_se_consume_al_vincular(): void
    {
        /*
         * Sin esto, la misma clave vincularia otra tableta, y las dos
         * enviarian respuestas de la misma ventanilla.
         */
        [$device, $clave] = $this->estacion();

        $this->post(route('kiosk.link.store'), ['key' => $clave]);

        $this->post(route('kiosk.link.store'), ['key' => $clave])
            ->assertSessionHasErrors('key');

        $this->assertSame(1, KioskCredential::query()->count());
    }

    public function test_revincular_revoca_la_credencial_anterior(): void
    {
        // Vincular de nuevo significa que la tableta se perdio o se
        // reinstalo: dejar la vieja viva permitiria que las dos enviaran.
        [$device, $clave] = $this->estacion();

        [, $primerToken] = app(LinkStation::class)->link($clave);

        $nuevaClave = app(ManageStationKey::class)->generate($device->fresh(), $this->admin($device));
        app(LinkStation::class)->link($nuevaClave);

        $this->assertSame(2, KioskCredential::query()->count());
        $this->assertSame(1, KioskCredential::query()->whereNull('revoked_at')->count());

        // Y el token viejo ya no resuelve.
        $this->expectException(StationNotReady::class);
        app(LinkStation::class)->resolve($primerToken);
    }

    public function test_sin_credencial_manda_a_vincular(): void
    {
        $this->get(route('kiosk.welcome'))->assertRedirect(route('kiosk.link'));
    }

    public function test_sin_deployment_muestra_estacion_no_configurada(): void
    {
        /*
         * RF-COL-007. Y RNF-COL-004: la pantalla NO expone IDs internos,
         * tokens ni rutas —solo el nombre de la tableta, que es lo que hay
         * que decirle al administrador—.
         */
        [$device, $clave] = $this->estacion();
        [, $token] = app(LinkStation::class)->link($clave);

        $this->withCookie(ResolveKioskStation::COOKIE, $token)
            ->get(route('kiosk.welcome'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Kiosk/NotReady')
                ->where('reason', 'no_deployment')
                ->missing('deviceUlid')
                ->missing('deploymentId')
            );
    }

    public function test_sin_sesion_abierta_manda_a_preparar(): void
    {
        // No se puede contestar sin saber de quien es el turno.
        [$device, $clave, $deployment] = $this->estacionConEncuesta();
        [, $token] = app(LinkStation::class)->link($clave);

        $this->withCookie(ResolveKioskStation::COOKIE, $token)
            ->get(route('kiosk.welcome'))
            ->assertRedirect(route('kiosk.prepare'));
    }

    public function test_la_preparacion_solo_ofrece_personal_de_esta_sucursal(): void
    {
        /*
         * Ofrecer toda la organizacion llenaria la lista de gente que no
         * trabaja ahi, y elegir mal atribuiria respuestas a otra persona.
         */
        [$device, $clave] = $this->estacionConEncuesta();
        [, $token] = app(LinkStation::class)->link($clave);

        StaffMember::factory()->create([
            'organization_id' => $device->organization_id,
            'branch_id' => $device->branch_id,
        ]);

        // De otra sucursal de la MISMA organizacion.
        $otraSucursal = Branch::factory()->create([
            'organization_id' => $device->organization_id,
        ]);
        StaffMember::factory()->create([
            'organization_id' => $device->organization_id,
            'branch_id' => $otraSucursal->id,
        ]);

        $this->withCookie(ResolveKioskStation::COOKIE, $token)
            ->get(route('kiosk.prepare'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('staff', 1));
    }

    public function test_la_bienvenida_no_ensena_nada_administrativo(): void
    {
        /*
         * RF-COL-011. Ni siquiera el nombre de quien esta siendo evaluado:
         * saberlo cambia lo que contesta quien esta delante.
         */
        [$device, $clave, $deployment] = $this->estacionConEncuesta();
        [, $token] = app(LinkStation::class)->link($clave);

        $persona = StaffMember::factory()->create([
            'organization_id' => $device->organization_id,
            'branch_id' => $device->branch_id,
        ]);

        app(OpenKioskSession::class)->execute($device->fresh(), $deployment, $persona);

        $this->withCookie(ResolveKioskStation::COOKIE, $token)
            ->get(route('kiosk.welcome'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Kiosk/Welcome')
                /*
                 * RF-COL-011: ni navegacion, ni datos del colaborador.
                 *
                 * NO se comprueba auth.user: HandleInertiaRequests lo
                 * comparte en TODAS las paginas. Que ahi haya un usuario no
                 * significa que el quiosco lo ensene —la pantalla no lo
                 * usa— pero conviene saberlo: si algun dia el quiosco
                 * corriera con la sesion de alguien, sus datos viajarian en
                 * las props sin que nadie lo hubiera decidido.
                 *
                 * Queda anotado como deuda: excluir el quiosco de ese share.
                 */
                /*
                 * La clave 'nav' EXISTE pero llega vacia.
                 *
                 * HandleInertiaRequests la comparte en todas las paginas y
                 * devuelve [] para el quiosco, asi que missing() no vale
                 * aqui: comprueba que la clave no este, no que este vacia.
                 *
                 * Se comprueba el CONTENIDO, que es lo que importa: ninguna
                 * entrada de menu viaja a la pantalla que ve un ciudadano
                 * (RF-COL-011).
                 */
                ->where('nav', [])
                ->missing('staffName')
            );
    }

    public function test_la_respuesta_guarda_la_sesion_y_a_quien_se_evaluo(): void
    {
        [$device, $clave, $deployment, $pregunta] = $this->estacionConEncuesta(true);
        [, $token] = app(LinkStation::class)->link($clave);

        $persona = StaffMember::factory()->create([
            'organization_id' => $device->organization_id,
            'branch_id' => $device->branch_id,
            'first_name' => 'Ana',
            'last_name' => 'Ruiz',
        ]);

        $sesion = app(OpenKioskSession::class)->execute($device->fresh(), $deployment, $persona);

        $this->withCookie(ResolveKioskStation::COOKIE, $token)
            ->post(route('kiosk.submit'), [
                'idempotency_key' => (string) Str::uuid(),
                'session' => $sesion->ulid,
                'answers' => [$pregunta->ulid => $pregunta->options->first()->ulid],
            ]);

        $respuesta = Response::query()->first();

        $this->assertNotNull($respuesta);
        $this->assertSame($sesion->id, $respuesta->kiosk_session_id);
        $this->assertSame($persona->id, $respuesta->staff_member_id);

        // Y el nombre como SNAPSHOT: si cambia despues, la respuesta sigue
        // diciendo a quien se evaluo aquel dia.
        $this->assertSame('Ana Ruiz', $respuesta->staff_member_name);
    }

    public function test_una_sesion_ajena_no_se_puede_suplantar(): void
    {
        /*
         * RNF-COL-013. La sesion se busca por DISPOSITIVO: si se confiara en
         * el ULID recibido, bastaria con cambiarlo para atribuir respuestas
         * al turno de otra persona.
         */
        [$device, $clave, $deployment, $pregunta] = $this->estacionConEncuesta(true);
        [, $token] = app(LinkStation::class)->link($clave);

        app(OpenKioskSession::class)->execute($device->fresh(), $deployment);

        $this->withCookie(ResolveKioskStation::COOKIE, $token)
            ->post(route('kiosk.submit'), [
                'idempotency_key' => (string) Str::uuid(),
                'session' => (string) Str::ulid(),
                'answers' => [$pregunta->ulid => $pregunta->options->first()->ulid],
            ])
            ->assertRedirect(route('kiosk.welcome'));

        $this->assertSame(0, Response::query()->count());
    }

    /** @return array{0: Device, 1: string} */
    private function estacion(): array
    {
        $membership = Membership::factory()->create();
        $branch = Branch::factory()->for($membership->organization)->create();

        $device = Device::factory()->for($branch)->create([
            'organization_id' => $membership->organization_id,
        ]);

        $clave = app(ManageStationKey::class)->generate($device, $membership->user);

        return [$device->fresh(), $clave];
    }

    /** @return array{0: Device, 1: string, 2: Deployment, 3?: SurveyQuestion} */
    private function estacionConEncuesta(bool $conPregunta = false): array
    {
        [$device, $clave] = $this->estacion();

        $survey = Survey::factory()->create(['organization_id' => $device->organization_id]);
        $version = SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $device->organization_id,
        ]);

        $pregunta = null;

        if ($conPregunta) {
            $pregunta = SurveyQuestion::factory()->for($version, 'version')->create([
                'organization_id' => $device->organization_id,
                'type' => QuestionType::Smiley,
                'text' => '¿Que tal?',
                'position' => 1,
            ]);

            SurveyQuestionOption::factory()->for($pregunta, 'question')->create([
                'organization_id' => $device->organization_id,
                'label' => 'Bien',
                'value' => 'bien',
                'score' => 5,
                'position' => 1,
            ]);
        }

        $deployment = Deployment::factory()->create([
            'organization_id' => $device->organization_id,
            'survey_version_id' => $version->id,
            'channel' => DeploymentChannel::Kiosk,
            'scope' => DeploymentScope::Device,
            'device_id' => $device->id,
        ]);

        return $conPregunta
            ? [$device, $clave, $deployment->fresh(), $pregunta->fresh()]
            : [$device, $clave, $deployment->fresh()];
    }

    private function admin(Device $device): User
    {
        return Membership::factory()->create([
            'organization_id' => $device->organization_id,
        ])->user;
    }
}
