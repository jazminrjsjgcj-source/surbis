<?php

declare(strict_types=1);

namespace Tests\Feature\Responses;

use App\Application\Responses\Exceptions\ResponseRejected;
use App\Application\Responses\SubmitResponse;
use App\Domain\Deployments\Enums\DeploymentStatus;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use App\Domain\Responses\BlindIndex;
use App\Domain\Responses\Models\Response;
use App\Domain\Surveys\Enums\IdentityMode;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guardar respuestas. RNF-COL-013 · RF-COL-020, 023, 024 · RNF-COL-014.
 */
final class SubmitResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_puntuacion_la_calcula_el_servidor(): void
    {
        /*
         * RNF-COL-013. LA PRUEBA QUE DA SENTIDO A LA FASE.
         *
         * El cliente manda QUE opcion se eligio. Cuanto vale lo busca el
         * servidor en la opcion guardada. Si se recibiera, bastaria con
         * editarla antes de enviar.
         */
        [$deployment, $pregunta] = $this->escenario();
        $mejor = $pregunta->options->firstWhere('value', 'bien');

        $respuesta = app(SubmitResponse::class)->execute(
            $deployment,
            [$pregunta->ulid => $mejor->ulid],
            (string) Str::uuid(),
        );

        $this->assertSame(5, $respuesta->score);
        $this->assertSame(5, $respuesta->max_score);
        $this->assertSame(5, $respuesta->answers->first()->score);
    }

    public function test_el_mismo_envio_dos_veces_no_duplica(): void
    {
        /*
         * Sin conexion el quiosco reintenta. Si el primer envio llego y la
         * confirmacion no, el segundo debe devolver la MISMA respuesta.
         *
         * Sin esto los resultados salen inflados y nadie lo nota.
         */
        [$deployment, $pregunta] = $this->escenario();
        $opcion = $pregunta->options->first();
        $clave = (string) Str::uuid();

        $primera = app(SubmitResponse::class)->execute(
            $deployment, [$pregunta->ulid => $opcion->ulid], $clave
        );
        $segunda = app(SubmitResponse::class)->execute(
            $deployment, [$pregunta->ulid => $opcion->ulid], $clave
        );

        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame(1, Response::query()->count());
    }

    public function test_la_ubicacion_sale_del_deployment_no_del_navegador(): void
    {
        // RNF-COL-013. Si la mandara el cliente, bastaria con cambiarla para
        // atribuir respuestas a otra oficina.
        [$deployment, $pregunta] = $this->escenario();

        $respuesta = app(SubmitResponse::class)->execute(
            $deployment, [$pregunta->ulid => $pregunta->options->first()->ulid], (string) Str::uuid(),
        );

        $this->assertSame($deployment->organization_id, $respuesta->organization_id);
        $this->assertSame($deployment->id, $respuesta->deployment_id);
    }

    public function test_se_guarda_como_se_llamaban_las_cosas(): void
    {
        /*
         * Decision del area usuaria: referencias Y snapshots.
         *
         * Si la encuesta se renombra despues, esta respuesta se contesto con
         * el nombre de ahora. Comparar periodos con los nombres cambiando
         * bajo los pies produce informes que mienten.
         */
        [$deployment, $pregunta] = $this->escenario();

        $respuesta = app(SubmitResponse::class)->execute(
            $deployment, [$pregunta->ulid => $pregunta->options->first()->ulid], (string) Str::uuid(),
        );

        $nombreOriginal = $respuesta->survey_name;

        $deployment->version->survey->forceFill(['name' => 'Otro nombre'])->save();

        $this->assertSame($nombreOriginal, $respuesta->fresh()->survey_name);
        $this->assertNotSame('Otro nombre', $respuesta->fresh()->survey_name);
    }

    public function test_una_aplicacion_que_no_aplica_no_recibe_respuestas(): void
    {
        [$deployment, $pregunta] = $this->escenario();
        $deployment->forceFill(['status' => DeploymentStatus::Suspended])->save();

        $this->expectException(ResponseRejected::class);

        app(SubmitResponse::class)->execute(
            $deployment->fresh(), [$pregunta->ulid => $pregunta->options->first()->ulid], (string) Str::uuid(),
        );
    }

    public function test_una_obligatoria_sin_contestar_se_rechaza(): void
    {
        // RF-COL-016. El cliente ya lo valida, pero quien envie a mano puede
        // saltarselo.
        [$deployment, $pregunta] = $this->escenario(required: true);

        $this->expectException(ResponseRejected::class);

        app(SubmitResponse::class)->execute($deployment, [], (string) Str::uuid());
    }

    public function test_contestar_una_pregunta_oculta_se_rechaza(): void
    {
        /*
         * La logica condicional se recalcula EN EL SERVIDOR. Recibir una
         * respuesta a una pregunta que nunca se mostro es senal de envio
         * manipulado.
         */
        [$deployment, $pregunta] = $this->escenario();

        $oculta = SurveyQuestion::factory()->for($deployment->version, 'version')->create([
            'organization_id' => $deployment->organization_id,
            'type' => QuestionType::LongText,
            'text' => 'Solo si dijo que mal',
            'position' => 2,
        ]);

        $oculta->condition()->create([
            'organization_id' => $deployment->organization_id,
            'depends_on_question_id' => $pregunta->id,
            'option_id' => $pregunta->options->firstWhere('value', 'mal')->id,
        ]);

        $this->expectException(ResponseRejected::class);

        // Contesta "bien", que NO muestra la de seguimiento, pero manda las dos.
        app(SubmitResponse::class)->execute($deployment->fresh(), [
            $pregunta->ulid => $pregunta->options->firstWhere('value', 'bien')->ulid,
            $oculta->ulid => 'algo',
        ], (string) Str::uuid());
    }

    public function test_en_modo_anonimo_no_se_guardan_datos_personales(): void
    {
        /*
         * RF-COL-023. Si alguien los envia a mano, guardarlos convertiria una
         * encuesta anonima en identificada sin que nadie lo decidiera.
         */
        [$deployment, $pregunta] = $this->escenario();

        $this->expectException(ResponseRejected::class);

        app(SubmitResponse::class)->execute(
            $deployment,
            [$pregunta->ulid => $pregunta->options->first()->ulid],
            (string) Str::uuid(),
            null,
            ['email' => 'alguien@example.test', 'consent' => true],
        );
    }

    public function test_los_datos_personales_exigen_consentimiento(): void
    {
        // RF-COL-024.
        [$deployment, $pregunta] = $this->escenario(identity: IdentityMode::Optional);

        $this->expectException(ResponseRejected::class);

        app(SubmitResponse::class)->execute(
            $deployment,
            [$pregunta->ulid => $pregunta->options->first()->ulid],
            (string) Str::uuid(),
            null,
            ['email' => 'alguien@example.test'],
        );
    }

    public function test_el_correo_se_guarda_cifrado_y_se_puede_buscar(): void
    {
        /*
         * RNF-COL-014 con indice ciego. Decision del area usuaria.
         *
         * En la base hay texto cifrado, no el correo. Y aun asi se puede
         * buscar por correo exacto, gracias al HMAC que va al lado.
         */
        [$deployment, $pregunta] = $this->escenario(identity: IdentityMode::Optional);

        $respuesta = app(SubmitResponse::class)->execute(
            $deployment,
            [$pregunta->ulid => $pregunta->options->first()->ulid],
            (string) Str::uuid(),
            null,
            ['email' => 'Ana@Example.test', 'consent' => true],
        );

        // Descifrado, se lee bien.
        $this->assertSame('Ana@Example.test', $respuesta->fresh()->respondent_email);

        // En la base NO esta en claro.
        $this->assertDatabaseMissing('responses', ['respondent_email' => 'Ana@Example.test']);

        /*
         * Y se puede buscar, incluso escrito con otras mayusculas: el indice
         * normaliza antes de calcular el HMAC.
         */
        $indice = app(BlindIndex::class)->of('ana@example.test');

        $this->assertTrue(
            Response::query()->where('respondent_email_index', $indice)->exists()
        );
    }

    /** @return array{0: Deployment, 1: SurveyQuestion} */
    private function escenario(
        bool $required = false,
        IdentityMode $identity = IdentityMode::Anonymous,
    ): array {
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();

        $version = SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $membership->organization_id,
            'settings' => ['identity_mode' => $identity->value],
        ]);

        $question = SurveyQuestion::factory()->for($version, 'version')->create([
            'organization_id' => $membership->organization_id,
            'type' => QuestionType::Smiley,
            'text' => '¿Como te atendieron?',
            'is_required' => $required,
            'position' => 1,
        ]);

        foreach ([['Bien', 'bien', 5], ['Mal', 'mal', 1]] as $i => [$label, $value, $score]) {
            SurveyQuestionOption::factory()->for($question, 'question')->create([
                'organization_id' => $membership->organization_id,
                'label' => $label,
                'value' => $value,
                'score' => $score,
                'position' => $i + 1,
            ]);
        }

        $deployment = Deployment::factory()->create([
            'organization_id' => $membership->organization_id,
            'survey_version_id' => $version->id,
        ]);

        return [$deployment->fresh(), $question->fresh()];
    }
}
