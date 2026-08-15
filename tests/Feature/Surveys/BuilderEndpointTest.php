<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Domain\Identity\Models\Membership;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * El endpoint del constructor. RF-AO-BLD-001 a 010.
 */
final class BuilderEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_entrar_al_constructor_abre_un_borrador(): void
    {
        // Quien viene a editar preguntas no tiene por que saber que antes hay
        // que crear una version.
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();

        $this->get(route('admin.surveys.builder', $survey))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Builder')
                ->where('readOnly', false)
                ->where('version.lock_version', 0)
            );

        $this->assertNotNull($survey->fresh()->draft);
    }

    public function test_una_version_publicada_se_abre_en_solo_lectura(): void
    {
        // RF-AO-BLD-009.
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->archived()->create();
        SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $admin->organization_id,
        ]);

        $this->get(route('admin.surveys.builder', $survey))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('readOnly', true));
    }

    public function test_guardar_devuelve_el_lock_version_nuevo(): void
    {
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->create(['organization_id' => $admin->organization_id]);

        $this->putJson(route('admin.surveys.builder.update', $survey), [
            'lock_version' => 0,
            'questions' => [$this->pregunta('¿Que tal la atencion?')],
        ])
            ->assertOk()
            ->assertJsonPath('lock_version', 1);

        $this->assertSame(1, SurveyQuestion::query()->count());
    }

    public function test_un_lock_version_viejo_da_409_con_el_estado_actual(): void
    {
        /*
         * El cliente necesita el estado del servidor para poder mostrar lo que
         * hay ahora sin una segunda peticion, y para saber contra que numero
         * reintentar.
         */
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->create(['organization_id' => $admin->organization_id]);

        $this->putJson(route('admin.surveys.builder.update', $survey), [
            'lock_version' => 0,
            'questions' => [$this->pregunta('Primera')],
        ])->assertOk();

        $this->putJson(route('admin.surveys.builder.update', $survey), [
            'lock_version' => 0,
            'questions' => [$this->pregunta('Segunda')],
        ])
            ->assertStatus(409)
            ->assertJsonPath('expected', 0)
            ->assertJsonPath('actual', 1)
            ->assertJsonPath('version.questions.0.text', 'Primera');
    }

    public function test_sin_lock_version_no_se_guarda(): void
    {
        /*
         * Sin valor por defecto a proposito. Si se admitiera ausente y se
         * tratara como 0, un cliente que lo olvidara sobrescribiria el trabajo
         * de otro sin enterarse.
         */
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->create(['organization_id' => $admin->organization_id]);

        $this->putJson(route('admin.surveys.builder.update', $survey), [
            'questions' => [$this->pregunta('Sin version')],
        ])->assertStatus(422);

        $this->assertSame(0, SurveyQuestion::query()->count());
    }

    public function test_valores_repetidos_dan_422_diciendo_cual(): void
    {
        // RF-AO-BLD-010. Una violacion de restriccion solo diria que la hubo.
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->create(['organization_id' => $admin->organization_id]);

        $this->putJson(route('admin.surveys.builder.update', $survey), [
            'lock_version' => 0,
            'questions' => [
                [
                    ...$this->pregunta('Con repetidos'),
                    'options' => [
                        $this->opcion('Buena', 'buena'),
                        $this->opcion('Muy buena', 'buena'),
                    ],
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Hay valores de opcion repetidos en "Con repetidos": buena']);
    }

    public function test_la_etiqueta_de_una_opcion_es_obligatoria(): void
    {
        // Es el nombre accesible. RF-AO-BLD-005.
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->create(['organization_id' => $admin->organization_id]);

        $this->putJson(route('admin.surveys.builder.update', $survey), [
            'lock_version' => 0,
            'questions' => [
                [
                    ...$this->pregunta('Con opcion sin nombre'),
                    'options' => [[...$this->opcion('', 'sin-nombre'), 'label' => '']],
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_una_encuesta_ajena_no_se_edita(): void
    {
        $this->admin();
        $ajena = Survey::factory()->create();

        $this->get(route('admin.surveys.builder', $ajena))->assertForbidden();
        $this->putJson(route('admin.surveys.builder.update', $ajena), [
            'lock_version' => 0,
            'questions' => [],
        ])->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function pregunta(string $texto): array
    {
        return [
            'ulid' => null,
            'type' => 'single_choice',
            'text' => $texto,
            'help' => null,
            'is_required' => false,
            'limits' => [],
            'options' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function opcion(string $label, string $value): array
    {
        return [
            'ulid' => null,
            'label' => $label,
            'value' => $value,
            'score' => null,
            'display' => 'text',
            'appearance' => null,
        ];
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
