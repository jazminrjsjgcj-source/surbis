<?php

declare(strict_types=1);

namespace Tests\Feature\Responses;

use App\Application\Responses\SubmitResponse;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\ConfidentialAccessGrant;
use App\Domain\Identity\Models\Membership;
use App\Domain\Responses\Models\Response;
use App\Domain\Surveys\Enums\IdentityMode;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Consultar respuestas. RF-AO-RES-001 a 006 · RNF-AO-RES-003 y 004.
 */
final class ResponseScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_por_debajo_del_umbral_no_se_mandan_las_filas(): void
    {
        /*
         * RNF-AO-RES-003. LA PRUEBA QUE DA SENTIDO A LA FASE.
         *
         * Una encuesta anonima deja de serlo por deduccion: con tres
         * respuestas en una ventanilla, saber cual es de quien es cuestion de
         * mirar el turno.
         *
         * Y las filas NO viajan: ocultarlas en el componente las dejaria en
         * el JSON de props, donde cualquiera las lee.
         */
        $membership = $this->admin();
        $this->conRespuestas($membership, 3);

        $this->get(route('admin.responses.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Responses/Index')
                ->where('responses', null)
                ->where('thresholdMet', false)
                ->where('total', 3)
            );
    }

    public function test_alcanzado_el_umbral_si_se_ven(): void
    {
        $membership = $this->admin();
        $this->conRespuestas($membership, 5);

        $this->get(route('admin.responses.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('thresholdMet', true)
                ->has('responses.data', 5)
            );
    }

    public function test_el_umbral_se_cuenta_sobre_lo_filtrado(): void
    {
        /*
         * Es el punto: sin filtros hay diez y no se identifica a nadie;
         * filtrando por un canal quedan dos, y ahi si. Contarlo antes de
         * filtrar dejaria el agujero abierto.
         */
        $membership = $this->admin();
        $this->conRespuestas($membership, 8);

        $this->get(route('admin.responses.index', ['channel' => 'kiosk']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('thresholdMet', false)
                ->where('total', 0)
            );
    }

    public function test_el_umbral_es_configurable_por_organizacion(): void
    {
        // Un ayuntamiento de tres ventanillas y uno de doscientas no corren
        // el mismo riesgo.
        $membership = $this->admin();

        $membership->organization->forceFill([
            'settings' => ['anonymity_threshold' => 2],
        ])->save();

        $this->conRespuestas($membership, 3);

        $this->get(route('admin.responses.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('thresholdMet', true));
    }

    public function test_no_se_ven_respuestas_de_otra_organizacion(): void
    {
        // RNF-GEN-005.
        $membership = $this->admin();
        $this->conRespuestas($membership, 5);

        $ajena = Membership::factory()->create();
        $this->conRespuestas($ajena, 5);

        $this->get(route('admin.responses.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('total', 5)
                ->has('responses.data', 5)
            );
    }

    public function test_el_detalle_muestra_la_version_historica(): void
    {
        /*
         * RF-AO-RES-003. El texto de la pregunta sale del SNAPSHOT: si la
         * encuesta cambio despues, esta respuesta contesto a lo que se
         * pregunto entonces.
         */
        $membership = $this->admin();
        [$respuesta] = $this->conRespuestas($membership, 1);

        $respuesta->version->questions()->first()->forceFill([
            'text' => 'Texto cambiado despues',
        ])->save();

        $this->get(route('admin.responses.show', $respuesta))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('response.answers.0.question', '¿Que tal?')
            );
    }

    public function test_una_confidencial_no_revela_datos_sin_autorizacion(): void
    {
        /*
         * RF-AO-RES-004: se dice SI es identificada, sin revelar los datos a
         * quien no puede verlos.
         *
         * La autorizacion la aprueba OTRA persona y caduca sola.
         */
        $membership = $this->admin();
        [$respuesta] = $this->conRespuestas($membership, 1, IdentityMode::Confidential, [
            'email' => 'quien@example.test', 'consent' => true,
        ]);

        $this->get(route('admin.responses.show', $respuesta))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('response.hasIdentity', true)
                ->where('response.canViewIdentity', false)
                ->where('response.identity', null)
            );
    }

    public function test_con_autorizacion_vigente_si_se_revela(): void
    {
        $membership = $this->admin();
        [$respuesta] = $this->conRespuestas($membership, 1, IdentityMode::Confidential, [
            'email' => 'quien@example.test', 'consent' => true,
        ]);

        // La aprueba OTRA persona: granted_by distinto de user_id.
        $aprobador = Membership::factory()->for($membership->organization)->create();

        ConfidentialAccessGrant::query()->create([
            'organization_id' => $membership->organization_id,
            'user_id' => $membership->user_id,
            'reason' => 'Revision de una queja formal.',
            'granted_by' => $aprobador->user_id,
            'granted_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->get(route('admin.responses.show', $respuesta))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('response.canViewIdentity', true)
                ->where('response.identity.email', 'quien@example.test')
            );
    }

    public function test_ver_una_identidad_queda_auditado(): void
    {
        // RNF-AO-RES-004.
        $membership = $this->admin();
        [$respuesta] = $this->conRespuestas($membership, 1, IdentityMode::Optional, [
            'email' => 'quien@example.test', 'consent' => true,
        ]);

        $this->get(route('admin.responses.show', $respuesta));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'response.identity_viewed',
            'user_id' => $membership->user_id,
        ]);
    }

    public function test_invalidar_no_borra_ni_edita_la_respuesta(): void
    {
        /*
         * RF-AO-RES-005 y 006: la respuesta original no se toca. Lo que
         * cambia es una marca al lado.
         */
        $membership = $this->admin();
        [$respuesta] = $this->conRespuestas($membership, 1);

        $puntuacionOriginal = $respuesta->score;

        $this->post(route('admin.responses.invalidate', $respuesta), [
            'reason' => 'Prueba interna del equipo, no cuenta.',
        ])->assertRedirect();

        $fresca = $respuesta->fresh();

        $this->assertNotNull($fresca->invalidated_at);
        $this->assertSame($membership->user_id, $fresca->invalidated_by);
        $this->assertSame($puntuacionOriginal, $fresca->score);
        $this->assertSame(1, Response::query()->count());
    }

    public function test_invalidar_sin_motivo_se_rechaza(): void
    {
        // "Invalidada" sin motivo no se puede revisar dentro de un año.
        $membership = $this->admin();
        [$respuesta] = $this->conRespuestas($membership, 1);

        $this->post(route('admin.responses.invalidate', $respuesta), ['reason' => 'x'])
            ->assertSessionHasErrors('reason');

        $this->assertNull($respuesta->fresh()->invalidated_at);
    }

    /** @return list<Response> */
    private function conRespuestas(
        Membership $membership,
        int $cuantas,
        IdentityMode $identity = IdentityMode::Anonymous,
        array $datos = [],
    ): array {
        $survey = Survey::factory()->for($membership->organization)->create();

        $version = SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $membership->organization_id,
            'settings' => ['identity_mode' => $identity->value],
        ]);

        $question = SurveyQuestion::factory()->for($version, 'version')->create([
            'organization_id' => $membership->organization_id,
            'type' => QuestionType::Smiley,
            'text' => '¿Que tal?',
            'position' => 1,
        ]);

        SurveyQuestionOption::factory()->for($question, 'question')->create([
            'organization_id' => $membership->organization_id,
            'label' => 'Bien',
            'value' => 'bien',
            'score' => 5,
            'position' => 1,
        ]);

        $deployment = Deployment::factory()->create([
            'organization_id' => $membership->organization_id,
            'survey_version_id' => $version->id,
        ]);

        $creadas = [];

        foreach (range(1, $cuantas) as $i) {
            $creadas[] = app(SubmitResponse::class)->execute(
                $deployment->fresh(),
                [$question->ulid => $question->options->first()->ulid],
                (string) Str::uuid(),
                null,
                $datos,
            );
        }

        return $creadas;
    }

    private function admin(): Membership
    {
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        return $membership;
    }
}
