<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Application\Analytics\QueryMetrics;
use App\Application\Analytics\RebuildMetrics;
use App\Application\Responses\InvalidateResponse;
use App\Application\Responses\SubmitResponse;
use App\Domain\Analytics\MetricSummary;
use App\Domain\Analytics\Models\ResponseMetric;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Responses\Models\Response;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Analitica. RNF-AO-RES-003 · decisiones del area usuaria, 18 ago 2026.
 */
final class MetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_por_debajo_del_umbral_no_se_da_ni_el_numero(): void
    {
        /*
         * LA PRUEBA QUE DA SENTIDO A LA FASE.
         *
         * Decir "datos insuficientes: hay 3" ya es informacion: con dos dias
         * de datos se deduce quien atendia esa ventanilla.
         */
        [$membership, $deployment, $pregunta] = $this->escenario();

        $this->responder($deployment, $pregunta, 3);

        $resumen = app(QueryMetrics::class)->summary($membership->organization, []);

        $this->assertFalse($resumen->available);
        $this->assertNull($resumen->responses);
        $this->assertNull($resumen->average);
        $this->assertNull($resumen->percentage);
    }

    public function test_alcanzado_el_umbral_si_hay_indicadores(): void
    {
        [$membership, $deployment, $pregunta] = $this->escenario();

        $this->responder($deployment, $pregunta, 5);

        $resumen = app(QueryMetrics::class)->summary($membership->organization, []);

        $this->assertTrue($resumen->available);
        $this->assertSame(5, $resumen->responses);
        $this->assertSame(5.0, $resumen->average);
        $this->assertSame(100.0, $resumen->percentage);
    }

    public function test_cada_punto_de_la_serie_pasa_el_umbral_por_separado(): void
    {
        /*
         * Un mes con mil respuestas puede tener un martes con dos, y ese
         * punto identificaria a alguien aunque el total no lo haga.
         */
        [$membership, $deployment, $pregunta] = $this->escenario();

        $this->travelTo(now()->subDays(2));
        $this->responder($deployment, $pregunta, 2);

        $this->travelBack();
        $this->responder($deployment, $pregunta, 6);

        $serie = app(QueryMetrics::class)->daily($membership->organization, []);

        $this->assertCount(2, $serie);
        $this->assertFalse($serie[0]['available']);
        $this->assertNull($serie[0]['responses']);
        $this->assertTrue($serie[1]['available']);
    }

    public function test_agrupar_por_sucursal_tambien_respeta_el_umbral(): void
    {
        // Comparar ventanillas es exactamente donde una con tres respuestas
        // señala a quien la atendia.
        [$membership, $deployment, $pregunta] = $this->escenario();

        $this->responder($deployment, $pregunta, 3);

        $grupos = app(QueryMetrics::class)->groupedBy($membership->organization, 'branch', []);

        foreach ($grupos as $grupo) {
            $this->assertFalse($grupo['available']);
            $this->assertNull($grupo['responses']);
        }
    }

    public function test_las_invalidadas_no_cuentan_pero_se_informan(): void
    {
        /*
         * Decision del area usuaria: no cuentan en los indicadores, pero se
         * dice cuantas se excluyeron. Un numero que baja sin explicacion
         * genera desconfianza.
         */
        [$membership, $deployment, $pregunta] = $this->escenario();

        $respuestas = $this->responder($deployment, $pregunta, 6);

        app(InvalidateResponse::class)->execute(
            $respuestas[0], $membership->user, 'Prueba interna del equipo.'
        );

        $resumen = app(QueryMetrics::class)->summary($membership->organization, []);

        $this->assertSame(5, $resumen->responses);
        $this->assertSame(1, $resumen->invalidated);
    }

    public function test_reconstruir_da_el_mismo_resultado(): void
    {
        /*
         * Las RESPUESTAS son la fuente oficial. Si los dos numeros no
         * coinciden, gana `responses` y esta tabla se rehace.
         *
         * Sin reconstruccion, el resumen seria una segunda verdad imposible
         * de contrastar.
         */
        [$membership, $deployment, $pregunta] = $this->escenario();

        $this->responder($deployment, $pregunta, 7);

        $antes = app(QueryMetrics::class)->summary($membership->organization, []);

        // Se ensucia a mano, como haria un fallo a medio proceso.
        ResponseMetric::query()->update(['responses' => 999, 'score_sum' => 12345]);

        app(RebuildMetrics::class)->forOrganization($membership->organization);

        $despues = app(QueryMetrics::class)->summary($membership->organization, []);

        $this->assertSame($antes->responses, $despues->responses);
        $this->assertSame($antes->average, $despues->average);
    }

    public function test_el_promedio_ignora_las_respuestas_sin_puntuacion(): void
    {
        /*
         * Una encuesta mixta tiene respuestas sin puntuacion. Dividir entre
         * el total las contaria como ceros y hundiria el promedio.
         */
        $resumen = MetricSummary::of(
            responses: 10, scoreSum: 40, maxScoreSum: 50, scored: 8, invalidated: 0
        );

        // 40 entre las 8 que puntuan, no entre 10.
        $this->assertSame(5.0, $resumen->average);
    }

    /** @return array{0: Membership, 1: Deployment, 2: SurveyQuestion} */
    private function escenario(): array
    {
        $membership = Membership::factory()->create();
        $branch = Branch::factory()->for($membership->organization)->create();

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

        /*
         * Alcance de SUCURSAL, no de organizacion.
         *
         * deployments_single_scope rechaza un deployment de organizacion con
         * branch_id: el alcance declarado tiene que coincidir con lo que se
         * pasa. Y aqui hace falta la sucursal para que los indicadores
         * agrupen por ella.
         */
        $deployment = Deployment::factory()->create([
            'organization_id' => $membership->organization_id,
            'survey_version_id' => $version->id,
            'scope' => DeploymentScope::Branch,
            'branch_id' => $branch->id,
        ]);

        return [$membership, $deployment->fresh(), $question->fresh()];
    }

    /** @return list<Response> */
    private function responder(Deployment $deployment, SurveyQuestion $pregunta, int $cuantas): array
    {
        $creadas = [];

        foreach (range(1, $cuantas) as $i) {
            $respuesta = app(SubmitResponse::class)->execute(
                $deployment,
                [$pregunta->ulid => $pregunta->options->first()->ulid],
                (string) Str::uuid(),
            );

            // No se llama a RecordResponseMetric: SubmitResponse ya lo hace
            // dentro de su transaccion.
            $creadas[] = $respuesta;
        }

        return $creadas;
    }
}
