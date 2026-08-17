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
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * La pantalla de analisis. RNF-AO-RES-003.
 */
final class AnalyticsScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_por_debajo_del_umbral_no_viaja_ningun_numero(): void
    {
        /*
         * LA PRUEBA QUE DA SENTIDO A LA PANTALLA.
         *
         * Ocultar los valores en el componente los dejaria en el JSON de
         * props, donde cualquiera los lee. El umbral tiene que llegar hasta
         * aqui.
         */
        $membership = $this->admin();
        $this->conRespuestas($membership, 3);

        $this->get(route('admin.analytics'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Analytics/Index')
                ->where('summary.available', false)
                ->where('summary.responses', null)
                ->where('summary.average', null)
                ->where('summary.percentage', null)
            );
    }

    public function test_alcanzado_el_umbral_hay_indicadores(): void
    {
        $membership = $this->admin();
        $this->conRespuestas($membership, 6);

        $this->get(route('admin.analytics'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.available', true)
                ->where('summary.responses', 6)
            );
    }

    public function test_no_se_ven_indicadores_de_otra_organizacion(): void
    {
        // RNF-GEN-005.
        $membership = $this->admin();
        $this->conRespuestas($membership, 6);

        $ajena = Membership::factory()->create();
        $this->conRespuestas($ajena, 20);

        $this->get(route('admin.analytics'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('summary.responses', 6));
    }

    public function test_la_pantalla_dice_cuando_se_calcularon(): void
    {
        /*
         * Decision del area usuaria. Sin esto, un numero desfasado es
         * indistinguible de uno al dia, y quien decida con el no sabra si
         * mira ayer o hace una semana.
         */
        $membership = $this->admin();
        $this->conRespuestas($membership, 6);

        $this->get(route('admin.analytics'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('updatedAt'));
    }

    public function test_los_grupos_llevan_nombre_no_identificadores(): void
    {
        // Una tarjeta que diga "7" en vez de "Palacio Municipal" no sirve
        // para decidir nada.
        $membership = $this->admin();
        $this->conRespuestas($membership, 6);

        $this->get(route('admin.analytics'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('byBranch.0.name')
            );
    }

    public function test_sin_permiso_no_se_entra(): void
    {
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        // Un colaborador no administra: no ve indicadores de nadie.
        $membership->forceFill(['role' => 'collaborator'])->save();

        $this->get(route('admin.analytics'))->assertForbidden();
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
