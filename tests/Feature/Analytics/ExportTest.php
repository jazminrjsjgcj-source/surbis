<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Application\Responses\SubmitResponse;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Models\Deployment;
use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Exportar indicadores. Decision del area usuaria, 19 ago 2026.
 */
final class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_por_debajo_del_umbral_el_archivo_no_lleva_numeros(): void
    {
        /*
         * LA PRUEBA QUE DA SENTIDO A LA EXPORTACION.
         *
         * Una exportacion que agregara por su cuenta seria la puerta de atras
         * del umbral: el mismo dato que la pantalla oculta saldria en un
         * archivo.
         */
        $membership = $this->admin();
        $this->conRespuestas($membership, 3);

        $contenido = $this->descargar();

        $this->assertStringContainsString('Datos insuficientes', $contenido);

        // Y NO aparece el recuento real.
        $this->assertStringNotContainsString(';3;', $contenido);
    }

    public function test_alcanzado_el_umbral_si_hay_numeros(): void
    {
        $membership = $this->admin();
        $this->conRespuestas($membership, 6);

        $contenido = $this->descargar();

        $this->assertStringContainsString('6', $contenido);
        $this->assertStringNotContainsString('Datos insuficientes', $contenido);
    }

    public function test_el_archivo_no_lleva_datos_de_quien_contesto(): void
    {
        /*
         * Decision del area usuaria: SOLO indicadores agregados. Nunca
         * respuestas individuales, comentarios, nombres ni correos.
         */
        $membership = $this->admin();
        $this->conRespuestas($membership, 6);

        $contenido = $this->descargar();

        foreach (['@', 'comentario', 'respondent'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, strtolower($contenido));
        }
    }

    public function test_el_csv_se_abre_bien_en_excel_espanol(): void
    {
        /*
         * BOM y punto y coma. Suena trivial y no lo es: sin BOM los acentos
         * salen rotos, y con comas Excel parte "4,5" en dos columnas porque
         * en espanol la coma es el separador decimal.
         */
        $membership = $this->admin();
        $this->conRespuestas($membership, 6);

        $contenido = $this->descargar();

        $this->assertStringStartsWith("\u{FEFF}", $contenido);
        $this->assertStringContainsString(';', $contenido);
    }

    public function test_exportar_queda_auditado(): void
    {
        // Sacar datos del sistema es una accion que conviene poder revisar.
        $membership = $this->admin();
        $this->conRespuestas($membership, 6);

        $this->descargar();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'analytics.exported',
            'user_id' => $membership->user_id,
        ]);
    }

    private function descargar(): string
    {
        $respuesta = $this->get(route('admin.analytics.export'));

        $respuesta->assertOk();

        return $respuesta->streamedContent();
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

    private function conRespuestas(Membership $membership, int $cuantas): void
    {
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

        $deployment = Deployment::factory()->create([
            'organization_id' => $membership->organization_id,
            'survey_version_id' => $version->id,
            'scope' => DeploymentScope::Branch,
            'branch_id' => $branch->id,
        ]);

        foreach (range(1, $cuantas) as $i) {
            app(SubmitResponse::class)->execute(
                $deployment->fresh(),
                [$question->fresh()->ulid => $question->options->first()->ulid],
                (string) Str::uuid(),
            );
        }
    }
}
