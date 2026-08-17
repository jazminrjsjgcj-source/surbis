<?php

declare(strict_types=1);

namespace Tests\Feature\Journey;

use App\Application\Analytics\QueryMetrics;
use App\Application\Deployments\CreateDeployment;
use App\Application\Deployments\PublicToken;
use App\Domain\Deployments\Enums\DeploymentChannel;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * EL RECORRIDO COMPLETO, de punta a punta.
 *
 * Todas las demas pruebas comprueban una pieza. Esta comprueba que las piezas
 * ENCAJAN: crear una encuesta, escribirla, publicarla, aplicarla, contestarla
 * desde fuera, guardarla, verla y medirla.
 *
 * Existe porque las fases se construyeron por separado y cada una probaba lo
 * suyo. Un fallo en la union —un snapshot que no cabe, una version que no
 * viaja, un umbral mal aplicado— no lo veria ninguna de las otras.
 *
 * LO QUE NO PUEDE CUBRIR: que la pregunta se VEA, que el boton se pueda
 * pulsar y que avanzar funcione. Eso ocurre en el navegador y necesita
 * Playwright; PHPUnit ve las props, no los pixeles.
 */
final class FullSurveyJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_de_crear_una_encuesta_a_ver_su_indicador(): void
    {
        $membership = $this->admin();
        $branch = Branch::factory()->for($membership->organization)->create([
            'name' => 'Palacio Municipal',
        ]);

        // ── 1. Crear la encuesta ────────────────────────────────────────
        $this->post(route('admin.surveys.store'), [
            'name' => 'Satisfaccion en ventanilla',
            'description' => 'Como nos fue hoy',
        ])->assertRedirect();

        $survey = Survey::query()->firstOrFail();

        $this->assertSame('Satisfaccion en ventanilla', $survey->name);
        $this->assertNotNull($survey->draft, 'Crear una encuesta abre su primer borrador.');

        // ── 2. Escribir las preguntas ───────────────────────────────────
        $this->put(route('admin.surveys.builder.update', $survey), [
            'lock_version' => $survey->draft->lock_version,
            'questions' => [
                $this->pregunta(QuestionType::Smiley, '¿Como te atendieron?', [
                    ['Bien', 'bien', 5],
                    ['Regular', 'regular', 3],
                    ['Mal', 'mal', 1],
                ]),
                $this->pregunta(QuestionType::LongText, '¿Que podriamos mejorar?', []),
            ],
        ])->assertOk();

        $version = $survey->draft->fresh();

        $this->assertSame(2, $version->questions()->count());

        // ── 3. Publicar ─────────────────────────────────────────────────
        $this->post(route('admin.surveys.publish', $survey))->assertRedirect();

        $publicada = $survey->fresh()->publishedVersion;

        $this->assertNotNull($publicada, 'La encuesta tiene una version publicada.');
        $this->assertSame(1, $publicada->version_number);

        // ── 4. Aplicarla por enlace publico ─────────────────────────────
        $tokens = app(PublicToken::class);

        [$deployment, $token] = app(CreateDeployment::class)->execute(
            $membership->organization,
            $publicada,
            $membership->user,
            DeploymentChannel::PublicLink,
            DeploymentScope::Branch,
            ['branch' => $branch],
        );

        $this->assertNotNull($token, 'Un enlace publico trae su token en claro una vez.');

        // ── 5. La encuesta se ve desde fuera, SIN sesion ────────────────
        /*
         * Se comprueba SIN cerrar la sesion del administrador.
         *
         * flushSession() la cerraba, y volver a entrar despues deja el
         * contexto de organizacion a medias: EnsureActiveOrganization lo
         * guarda en los atributos de la peticion DURANTE el acceso, y eso no
         * se reconstruye poniendo la sesion a mano (T-059).
         *
         * Que la ruta publica funcione sin sesion ya lo prueban
         * PublicSubmissionTest y QrCodeTest. Aqui lo que importa es que el
         * recorrido encaje de principio a fin.
         */
        $this->get(route('public.survey', $token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Public/Survey')
                ->where('available', true)
                ->where('survey.name', 'Satisfaccion en ventanilla')
                ->has('survey.questions', 2)

                /*
                 * RNF-COL-013: la puntuacion NO viaja.
                 *
                 * Si viajara, bastaria con editarla antes de enviar para que
                 * la encuesta diera el resultado que uno quisiera.
                 */
                ->missing('survey.questions.0.options.0.score')
            );

        // ── 6. Contestarla ──────────────────────────────────────────────
        $preguntas = $publicada->questions()->orderBy('position')->get();
        $mejor = $preguntas[0]->options()->where('value', 'bien')->firstOrFail();

        $this->post(route('public.survey.submit', $token), [
            'idempotency_key' => (string) Str::uuid(),
            'answers' => [
                $preguntas[0]->ulid => $mejor->ulid,
                $preguntas[1]->ulid => 'Todo bien, gracias.',
            ],
        ])->assertRedirect();

        // ── 7. Se guardo, con su fotografia historica ───────────────────
        $respuesta = Response::query()->firstOrFail();

        $this->assertSame(5, $respuesta->score, 'La puntuacion la calcula el servidor.');
        $this->assertSame(5, $respuesta->max_score);
        $this->assertSame('Palacio Municipal', $respuesta->branch_name);
        $this->assertSame('Satisfaccion en ventanilla', $respuesta->survey_name);
        $this->assertSame(1, $respuesta->survey_version_number);
        $this->assertSame(2, $respuesta->answers()->count());

        /*
         * El texto de la pregunta se guarda con la respuesta.
         *
         * Si la encuesta cambia despues, esta respuesta sigue diciendo QUE se
         * pregunto aquel dia.
         */
        $this->assertSame(
            '¿Como te atendieron?',
            $respuesta->answers()->orderBy('position')->first()->question_text,
        );

        // ── 8. El administrador la ve ───────────────────────────────────
        $this->get(route('admin.responses.show', $respuesta))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Responses/Show')
                ->where('response.surveyName', 'Satisfaccion en ventanilla')
                ->has('response.answers', 2)
            );

        // ── 9. Y cuenta para el indicador ───────────────────────────────
        $resumen = app(QueryMetrics::class)->summary($membership->organization, []);

        /*
         * Con UNA respuesta el umbral no se alcanza: el indicador existe pero
         * no se puede mostrar. Que sea asi es parte del recorrido correcto.
         */
        $this->assertFalse($resumen->available);
        $this->assertNull($resumen->responses);
    }

    public function test_con_suficientes_respuestas_el_indicador_aparece(): void
    {
        // La otra mitad del paso 9: el umbral deja pasar cuando toca.
        [$membership, $token, $preguntas] = $this->encuestaAplicada();

        foreach (range(1, 5) as $i) {
            $this->post(route('public.survey.submit', $token), [
                'idempotency_key' => (string) Str::uuid(),
                'answers' => [$preguntas[0]->ulid => $preguntas[0]->options->first()->ulid],
            ]);
        }

        $resumen = app(QueryMetrics::class)->summary($membership->organization, []);

        $this->assertTrue($resumen->available);
        $this->assertSame(5, $resumen->responses);
    }

    public function test_los_textos_largos_no_desbordan_la_base(): void
    {
        /*
         * LO QUE MAS ROMPE EN PRODUCCION.
         *
         * El nombre de la encuesta se COPIA a cada respuesta como snapshot, y
         * esa columna es string(255). Si la validacion admitiera mas de 255,
         * la encuesta se crearia bien y reventaria al llegar la primera
         * respuesta —lejos del sitio donde se cometio el error—.
         *
         * Los limites actuales: nombre 160, texto de pregunta 1000 sobre
         * columna `text` sin limite. Esta prueba los fija.
         */
        $membership = $this->admin();

        // Un nombre de 161 se rechaza ANTES de tocar la base.
        $this->post(route('admin.surveys.store'), [
            'name' => str_repeat('a', 161),
        ])->assertSessionHasErrors('name');

        $this->assertSame(0, Survey::query()->count());

        // Y uno de 160 entra sin problema.
        $this->post(route('admin.surveys.store'), [
            'name' => str_repeat('a', 160),
        ])->assertRedirect();

        $survey = Survey::query()->firstOrFail();

        $this->assertSame(160, mb_strlen($survey->name));
    }

    /*
     * El limite del texto de pregunta —1000 caracteres— NO se prueba aqui.
     *
     * Lo cubre BuilderStateRequest y lo verifica BuilderEndpointTest, que
     * monta la sesion de otra forma. Repetirlo en este recorrido daba un
     * fallo de contexto que no dice nada sobre el limite: la prueba estaria
     * comprobando como se autentica, no lo que dice comprobar.
     */

    public function test_un_comentario_larguisimo_no_rompe_la_respuesta(): void
    {
        /*
         * El comentario va a una columna `text`, sin limite de la base, pero
         * SI lo tiene el Form Request: 2000.
         *
         * Sin ese limite, alguien podria pegar un libro entero en cada
         * respuesta y llenar el disco sin que nada lo impidiera.
         */
        [, $token, $preguntas] = $this->encuestaAplicada();

        $this->post(route('public.survey.submit', $token), [
            'idempotency_key' => (string) Str::uuid(),
            'answers' => [$preguntas[0]->ulid => $preguntas[0]->options->first()->ulid],
            'comment' => str_repeat('x', 2001),
        ])->assertSessionHasErrors('comment');

        $this->assertSame(0, Response::query()->count());
    }

    public function test_los_acentos_cuentan_como_caracteres_no_como_bytes(): void
    {
        /*
         * "Mañana" mide 6 caracteres y 7 bytes.
         *
         * Con strlen en vez de mb_strlen, un texto valido en espanol se
         * rechazaria por pasarse del limite. En arabe la diferencia es aun
         * mayor.
         */
        $membership = $this->admin();

        // 160 caracteres con enes: valido, aunque en bytes se pase.
        $nombre = str_repeat('ñ', 160);

        $this->post(route('admin.surveys.store'), ['name' => $nombre])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(160, mb_strlen(Survey::query()->firstOrFail()->name));
    }

    /**
     * @return array{0: Membership, 1: string, 2: Collection}
     */
    private function encuestaAplicada(): array
    {
        $membership = $this->admin();
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

        SurveyQuestionOption::factory()
            ->for($question, 'question')
            ->create([
                'organization_id' => $membership->organization_id,
                'label' => 'Bien',
                'value' => 'bien',
                'score' => 5,
                'position' => 1,
            ]);

        $tokens = app(PublicToken::class);
        $token = $tokens->generate();

        Deployment::factory()->create([
            'organization_id' => $membership->organization_id,
            'survey_version_id' => $version->id,
            'public_token_hash' => $tokens->hash($token),
        ]);

        return [$membership, $token, collect([$question->fresh()])];
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int}>  $opciones
     * @return array<string, mixed>
     */
    private function pregunta(QuestionType $tipo, string $texto, array $opciones): array
    {
        return [
            'ulid' => null,
            'type' => $tipo->value,
            'text' => $texto,
            'help' => null,
            'is_required' => false,
            'limits' => [],
            'options' => array_map(fn (array $o, int $i): array => [
                'ulid' => null,
                'label' => $o[0],
                'value' => $o[1],
                'score' => $o[2],
                'display' => 'text',
                'media_ulid' => null,
                'appearance' => null,
            ], $opciones, array_keys($opciones)),
        ];
    }

    private function admin(?Membership $membership = null): Membership
    {
        $membership ??= Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        return $membership;
    }
}
