<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\StoreMediaItem;
use App\Application\Surveys\SaveBuilderState;
use App\Domain\Identity\Models\Membership;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Domain\Surveys\PublicationChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Imagenes en las opciones. RF-AO-BLD-004 · RNF-GEN-005.
 */
final class OptionMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_opcion_guarda_su_imagen(): void
    {
        Storage::fake('local');
        [$version, $membership] = $this->borrador();

        $imagen = app(StoreMediaItem::class)->execute(
            $membership->organization, $membership->user, UploadedFile::fake()->image('carita.png')
        );

        app(SaveBuilderState::class)->execute($version, 0, [
            $this->pregunta('¿Que tal?', [$this->opcion('Bien', 'bien', $imagen->ulid)]),
        ]);

        $this->assertSame($imagen->id, SurveyQuestionOption::query()->first()->media_id);
    }

    public function test_una_imagen_de_otra_organizacion_no_se_guarda(): void
    {
        /*
         * RNF-GEN-005. Sin usableBy() en resolveMedia, bastaria con enviar el
         * ULID a mano para mostrar una imagen ajena en una encuesta propia:
         * los ULID viajan al navegador y son adivinables si se conoce uno.
         */
        Storage::fake('local');
        [$version, $membership] = $this->borrador();

        $ajena = Membership::factory()->create();
        $imagenAjena = app(StoreMediaItem::class)->execute(
            $ajena->organization, $ajena->user, UploadedFile::fake()->image('ajena.png')
        );

        app(SaveBuilderState::class)->execute($version, 0, [
            $this->pregunta('¿Que tal?', [$this->opcion('Bien', 'bien', $imagenAjena->ulid)]),
        ]);

        // Se guarda la opcion, pero SIN la imagen ajena.
        $this->assertNull(SurveyQuestionOption::query()->first()->media_id);
    }

    public function test_una_imagen_del_sistema_si_se_puede_usar(): void
    {
        Storage::fake('local');
        [$version, $membership] = $this->borrador();

        $sistema = MediaItem::query()->create([
            'organization_id' => null,
            'name' => 'Cara contenta',
            'disk' => 'local',
            'path' => 'media/system/smiley/bien.svg',
            'mime_type' => 'image/svg+xml',
            'size_bytes' => 700,
            'alt_text' => 'Cara contenta',
        ]);

        app(SaveBuilderState::class)->execute($version, 0, [
            $this->pregunta('¿Que tal?', [$this->opcion('Bien', 'bien', $sistema->ulid)]),
        ]);

        $this->assertSame($sistema->id, SurveyQuestionOption::query()->first()->media_id);
    }

    public function test_solo_imagen_sin_imagen_bloquea_la_publicacion(): void
    {
        /*
         * RF-AO-BLD-004. Una opcion "solo imagen" sin imagen es un hueco
         * invisible: en el quiosco no se ve nada que pulsar, y quien conteste
         * no sabra que falta una respuesta.
         */
        Storage::fake('local');
        [$version, $membership] = $this->borrador();

        app(SaveBuilderState::class)->execute($version, 0, [
            $this->pregunta('¿Que tal?', [
                [...$this->opcion('Bien', 'bien', null), 'display' => 'image'],
                $this->opcion('Mal', 'mal', null),
            ]),
        ]);

        $problemas = app(PublicationChecklist::class)->problems($version->fresh());

        $this->assertNotNull($problemas->firstWhere('key', 'option_without_image'));
    }

    /** @return array{0: SurveyVersion, 1: Membership} */
    private function borrador(): array
    {
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();

        $version = SurveyVersion::factory()->for($survey)->create([
            'organization_id' => $membership->organization_id,
        ]);

        return [$version, $membership];
    }

    /** @return array<string, mixed> */
    private function pregunta(string $texto, array $opciones): array
    {
        return [
            'ulid' => null,
            'type' => QuestionType::SingleChoice->value,
            'text' => $texto,
            'help' => null,
            'is_required' => false,
            'limits' => [],
            'options' => $opciones,
        ];
    }

    /** @return array<string, mixed> */
    private function opcion(string $label, string $value, ?string $mediaUlid): array
    {
        return [
            'ulid' => null,
            'label' => $label,
            'value' => $value,
            'score' => null,
            'display' => 'text',
            'media_ulid' => $mediaUlid,
            'appearance' => null,
        ];
    }
}
