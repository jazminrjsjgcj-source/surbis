<?php

declare(strict_types=1);

namespace Tests\Feature\Responses;

use App\Application\Deployments\PublicToken;
use App\Domain\Deployments\Models\Deployment;
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
 * Contestar desde el enlace publico. RF-COL-020 a 024.
 */
final class PublicSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_contesta_sin_haber_iniciado_sesion(): void
    {
        // Quien escanea un cartel no ha iniciado sesion en nada: lo que
        // autoriza es el token, no un usuario.
        [$token, $pregunta] = $this->escenario();

        $this->post(route('public.survey.submit', $token), [
            'idempotency_key' => (string) Str::uuid(),
            'answers' => [$pregunta->ulid => $pregunta->options->first()->ulid],
        ])->assertRedirect();

        $this->assertSame(1, Response::query()->count());
    }

    public function test_el_mismo_uuid_no_crea_dos_respuestas(): void
    {
        /*
         * El UUID lo genera el CLIENTE y sobrevive a los reintentos. Si el
         * envio llego y la confirmacion no, el segundo intento trae el mismo
         * y no debe duplicar.
         */
        [$token, $pregunta] = $this->escenario();
        $clave = (string) Str::uuid();
        $datos = [
            'idempotency_key' => $clave,
            'answers' => [$pregunta->ulid => $pregunta->options->first()->ulid],
        ];

        $this->post(route('public.survey.submit', $token), $datos);
        $this->post(route('public.survey.submit', $token), $datos);

        $this->assertSame(1, Response::query()->count());
    }

    public function test_un_token_invalido_no_guarda_nada(): void
    {
        [, $pregunta] = $this->escenario();

        $this->post(route('public.survey.submit', (string) Str::random(32)), [
            'idempotency_key' => (string) Str::uuid(),
            'answers' => [$pregunta->ulid => $pregunta->options->first()->ulid],
        ])->assertSessionHasErrors('response');

        $this->assertSame(0, Response::query()->count());
    }

    public function test_la_pantalla_dice_si_pide_identidad(): void
    {
        /*
         * RF-COL-022. El modo viaja como prop para que la pantalla sepa si
         * mostrar los campos: pedirlos en anonimo seria una peticion que el
         * servidor nunca puede cumplir.
         */
        [$token] = $this->escenario(identity: IdentityMode::Optional);

        $this->get(route('public.survey', $token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('survey.identityMode', 'optional')
                ->has('submitUrl')
            );
    }

    public function test_en_anonimo_la_pantalla_no_pide_identidad(): void
    {
        [$token] = $this->escenario();

        $this->get(route('public.survey', $token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('survey.identityMode', 'anonymous')
            );
    }

    public function test_el_comentario_se_guarda(): void
    {
        // RF-COL-021. Y RNF-COL-016: se guarda como TEXTO, nunca se ejecuta.
        [$token, $pregunta] = $this->escenario();

        $this->post(route('public.survey.submit', $token), [
            'idempotency_key' => (string) Str::uuid(),
            'answers' => [$pregunta->ulid => $pregunta->options->first()->ulid],
            'comment' => 'Me atendieron muy bien <script>alert(1)</script>',
        ]);

        $guardado = Response::query()->first()->comment;

        // El texto se conserva tal cual: escaparlo al GUARDAR seria alterar
        // lo que la persona escribio. Se escapa al MOSTRARLO, y React lo hace
        // por defecto.
        $this->assertStringContainsString('<script>', (string) $guardado);
    }

    /** @return array{0: string, 1: SurveyQuestion} */
    private function escenario(IdentityMode $identity = IdentityMode::Anonymous): array
    {
        $membership = Membership::factory()->create();
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

        $tokens = app(PublicToken::class);
        $token = $tokens->generate();

        Deployment::factory()->create([
            'organization_id' => $membership->organization_id,
            'survey_version_id' => $version->id,
            'public_token_hash' => $tokens->hash($token),
        ]);

        return [$token, $question->fresh()];
    }
}
