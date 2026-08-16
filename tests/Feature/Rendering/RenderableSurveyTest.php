<?php

declare(strict_types=1);

namespace Tests\Feature\Rendering;

use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Identity\Models\Membership;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Enums\RenderLayout;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Domain\Surveys\Rendering\RenderableSurvey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que se le manda al renderizador. RNF-COL-012 y 013.
 */
final class RenderableSurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_puntuacion_no_viaja_al_navegador(): void
    {
        /*
         * RNF-COL-013. LA PRUEBA QUE DA SENTIDO A TODA LA FASE.
         *
         * El navegador no decide cuanto vale una respuesta: manda que opcion
         * se eligio, y el servidor busca su puntuacion. Si viajara, bastaria
         * con editarla antes de enviar para que una encuesta de satisfaccion
         * diera el resultado que uno quisiera.
         */
        $version = $this->versionConEscala();

        $datos = (new RenderableSurvey($version, RenderLayout::Stepped))->toArray();
        $json = json_encode($datos);

        $this->assertStringNotContainsString('score', (string) $json);
        $this->assertArrayNotHasKey('score', $datos['questions'][0]['options'][0]);
    }

    public function test_el_modo_depende_del_canal(): void
    {
        /*
         * Decision del area usuaria: lo que decide no es el canal en si, sino
         * el contexto. De pie y con prisa, una pregunta cada vez.
         */
        $this->assertSame(RenderLayout::Stepped, RenderLayout::forChannel(DeploymentChannel::Kiosk));
        $this->assertSame(RenderLayout::Stepped, RenderLayout::forChannel(DeploymentChannel::Qr));
        $this->assertSame(RenderLayout::Full, RenderLayout::forChannel(DeploymentChannel::PublicLink));
        $this->assertSame(RenderLayout::Full, RenderLayout::forChannel(DeploymentChannel::Widget));
    }

    public function test_las_preguntas_van_en_su_orden(): void
    {
        // RF-COL-014.
        $version = $this->versionConEscala();

        SurveyQuestion::factory()->for($version, 'version')->create([
            'organization_id' => $version->organization_id,
            'type' => QuestionType::LongText,
            'text' => 'La segunda',
            'position' => 2,
        ]);

        $datos = (new RenderableSurvey($version->fresh(), RenderLayout::Full))->toArray();

        $this->assertSame('¿Como te atendieron?', $datos['questions'][0]['text']);
        $this->assertSame('La segunda', $datos['questions'][1]['text']);
    }

    public function test_la_imagen_lleva_su_nombre_accesible(): void
    {
        /*
         * RNF-COL-011: todo boton con imagen necesita nombre aunque el texto
         * visible este oculto. Sin el, quien usa lector de pantalla oye
         * "boton" cinco veces y no puede elegir.
         */
        $version = $this->versionConEscala();
        $datos = (new RenderableSurvey($version, RenderLayout::Stepped))->toArray();

        foreach ($datos['questions'][0]['options'] as $opcion) {
            $this->assertArrayHasKey('label', $opcion);
            $this->assertNotEmpty($opcion['label']);
        }
    }

    public function test_los_limites_van_segun_el_tipo(): void
    {
        // El renderizador no tiene que saber que un numero lleva min y max y
        // un texto lleva longitudes: se lo dice el servidor.
        $version = $this->versionConEscala();

        SurveyQuestion::factory()->for($version, 'version')->create([
            'organization_id' => $version->organization_id,
            'type' => QuestionType::Number,
            'text' => '¿Cuantos anos llevas viniendo?',
            'limits' => ['min' => 0, 'max' => 50],
            'position' => 2,
        ]);

        $datos = (new RenderableSurvey($version->fresh(), RenderLayout::Full))->toArray();

        /*
         * min y max son FLOAT a proposito: una pregunta numerica puede pedir
         * "entre 0 y 4.5". Los demas limites —longitudes, selecciones,
         * pasos— son enteros, porque "al menos 2.5 opciones" no significa
         * nada.
         *
         * Por eso se comparan con assertEquals y no con assertSame.
         */
        $this->assertEquals(0, $datos['questions'][1]['limits']['min']);
        $this->assertEquals(50, $datos['questions'][1]['limits']['max']);
    }

    public function test_la_condicion_viaja_por_ulid(): void
    {
        /*
         * Se manda para que la pantalla reaccione al instante: preguntar al
         * servidor en cada respuesta haria el quiosco inusable, y RNF-COL-009
         * pide reaccionar en menos de 100 ms.
         *
         * Que la condicion se cumpla de verdad lo comprueba OTRA VEZ el
         * servidor al recibir (Fase 9).
         */
        $version = $this->versionConEscala();
        $primera = $version->questions()->first();
        $opcion = $primera->options()->first();

        $seguimiento = SurveyQuestion::factory()->for($version, 'version')->create([
            'organization_id' => $version->organization_id,
            'type' => QuestionType::LongText,
            'text' => '¿Que paso?',
            'position' => 2,
        ]);

        $seguimiento->condition()->create([
            'organization_id' => $version->organization_id,
            'depends_on_question_id' => $primera->id,
            'option_id' => $opcion->id,
        ]);

        $datos = (new RenderableSurvey($version->fresh(), RenderLayout::Stepped))->toArray();

        $this->assertNull($datos['questions'][0]['condition']);
        $this->assertSame($primera->ulid, $datos['questions'][1]['condition']['dependsOn']);
        $this->assertSame($opcion->ulid, $datos['questions'][1]['condition']['option']);
    }

    private function versionConEscala(): SurveyVersion
    {
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();

        $version = SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $membership->organization_id,
        ]);

        $question = SurveyQuestion::factory()->for($version, 'version')->create([
            'organization_id' => $membership->organization_id,
            'type' => QuestionType::Smiley,
            'text' => '¿Como te atendieron?',
            'position' => 1,
        ]);

        foreach ([['Bien', 'bien', 2], ['Mal', 'mal', 1]] as $i => [$label, $value, $score]) {
            SurveyQuestionOption::factory()->for($question, 'question')->create([
                'organization_id' => $membership->organization_id,
                'label' => $label,
                'value' => $value,
                'score' => $score,
                'position' => $i + 1,
            ]);
        }

        return $version->fresh();
    }
}
