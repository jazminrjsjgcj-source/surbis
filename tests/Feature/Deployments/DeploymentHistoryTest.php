<?php

declare(strict_types=1);

namespace Tests\Feature\Deployments;

use App\Application\Deployments\DeploymentGuard;
use App\Application\Deployments\Exceptions\DeploymentRejected;
use App\Application\Responses\SubmitResponse;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Un deployment con respuestas no se toca. RF-AO-DEP-006.
 *
 * ESTA PRUEBA NO EXISTIA HASTA LA FASE 9, y no por descuido: hasta que hubo
 * tabla de respuestas, `countResponses()` devolvia cero siempre y la regla no
 * podia comprobarse.
 *
 * Eso dejo un metodo que parecia proteger algo durante tres tareas. Las 319
 * pruebas pasaban igual con el cero, que es exactamente lo que hace peligroso
 * un mecanismo que no hace nada: no da error, no falla, y nadie lo mira.
 */
final class DeploymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_respuestas_se_puede_tocar(): void
    {
        [$deployment] = $this->escenario();

        app(DeploymentGuard::class)->ensureNoHistory($deployment);

        $this->assertTrue(true, 'No lanza cuando no hay historial.');
    }

    public function test_con_respuestas_queda_bloqueado(): void
    {
        /*
         * Cambiarle el alcance dejaria respuestas ya recibidas apuntando a un
         * lugar donde nunca se dieron, y el historico pasaria a mentir sin
         * que nadie lo notara.
         *
         * RF-AO-DEP-006: reasignar es CERRAR el anterior y crear otro.
         */
        [$deployment, $pregunta] = $this->escenario();

        app(SubmitResponse::class)->execute(
            $deployment,
            [$pregunta->ulid => $pregunta->options->first()->ulid],
            (string) Str::uuid(),
        );

        $this->expectException(DeploymentRejected::class);

        app(DeploymentGuard::class)->ensureNoHistory($deployment->fresh());
    }

    public function test_el_bloqueo_dice_cuantas_respuestas_hay(): void
    {
        // "No se puede" sin decir por que obliga a averiguarlo a mano.
        [$deployment, $pregunta] = $this->escenario();

        foreach (range(1, 3) as $i) {
            app(SubmitResponse::class)->execute(
                $deployment,
                [$pregunta->ulid => $pregunta->options->first()->ulid],
                (string) Str::uuid(),
            );
        }

        try {
            app(DeploymentGuard::class)->ensureNoHistory($deployment->fresh());
            $this->fail('Se esperaba que bloqueara.');
        } catch (DeploymentRejected $bloqueo) {
            $this->assertSame('has_history', $bloqueo->key);
            $this->assertSame(3, $bloqueo->replacements['responses']);
        }
    }

    /** @return array{0: Deployment, 1: SurveyQuestion} */
    private function escenario(): array
    {
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();

        $version = SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $membership->organization_id,
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

        return [$deployment->fresh(), $question->fresh()];
    }
}
