<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\MediaPolicy;
use App\Domain\Identity\Models\Membership;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Surveys\Models\Survey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Subir imagenes a la biblioteca. RF-AO-MED-001.
 *
 * La subida existia escrita desde la Fase 5 y NADIE la llamaba: no habia
 * ruta ni controlador. Se descubrio al preguntar quien podia subir imagenes y
 * comprobar que la respuesta era nadie.
 */
final class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_sube_una_imagen(): void
    {
        Storage::fake('local');

        [$membership, $survey] = $this->encuesta();

        $this->post(route('admin.surveys.media.upload', $survey), [
            'file' => UploadedFile::fake()->image('cara.png', 200, 200),
        ])->assertRedirect();

        $this->assertSame(1, MediaItem::query()->count());
    }

    public function test_va_al_disco_privado(): void
    {
        /*
         * Decision de la Fase 5 (D-043): lo que sube cada organizacion va a
         * un disco privado y se sirve por MediaController, que comprueba a
         * quien pertenece.
         *
         * En el disco publico, cualquiera con la URL veria las imagenes de
         * cualquier ayuntamiento.
         */
        Storage::fake('local');
        Storage::fake('public');

        [$membership, $survey] = $this->encuesta();

        $this->post(route('admin.surveys.media.upload', $survey), [
            'file' => UploadedFile::fake()->image('cara.png'),
        ]);

        $item = MediaItem::query()->firstOrFail();

        Storage::disk('local')->assertExists($item->path);
        Storage::disk('public')->assertMissing($item->path);
    }

    public function test_un_svg_se_rechaza(): void
    {
        /*
         * Un SVG puede contener JavaScript, y una imagen que se muestra a
         * ciudadanos en un quiosco no puede ejecutar codigo.
         *
         * Es un agujero clasico y silencioso: el archivo parece una imagen,
         * se ve como una imagen, y ejecuta lo que lleve dentro.
         */
        Storage::fake('local');

        [$membership, $survey] = $this->encuesta();

        $this->post(route('admin.surveys.media.upload', $survey), [
            'file' => UploadedFile::fake()->create('malicioso.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, MediaItem::query()->count());
    }

    public function test_una_imagen_demasiado_grande_se_rechaza(): void
    {
        // 2 MB. Una foto de movil sin comprimir pasa de 4.
        Storage::fake('local');

        [$membership, $survey] = $this->encuesta();

        $tamano = (int) (MediaPolicy::MAX_BYTES / 1024) + 100;

        $this->post(route('admin.surveys.media.upload', $survey), [
            'file' => UploadedFile::fake()->create('enorme.jpg', $tamano, 'image/jpeg'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, MediaItem::query()->count());
    }

    public function test_una_encuesta_ajena_no_recibe_imagenes(): void
    {
        Storage::fake('local');

        $this->encuesta();

        $ajena = Survey::factory()->create();

        $this->post(route('admin.surveys.media.upload', $ajena), [
            'file' => UploadedFile::fake()->image('cara.png'),
        ])->assertForbidden();

        $this->assertSame(0, MediaItem::query()->count());
    }

    public function test_devuelve_el_ulid_de_lo_subido(): void
    {
        // Sin el, quien acaba de subir tendria que buscar su imagen en la
        // biblioteca para usarla.
        Storage::fake('local');

        [$membership, $survey] = $this->encuesta();

        $this->post(route('admin.surveys.media.upload', $survey), [
            'file' => UploadedFile::fake()->image('cara.png'),
        ])->assertSessionHas('uploaded_media');
    }

    /** @return array{0: Membership, 1: Survey} */
    private function encuesta(): array
    {
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        $survey = Survey::factory()->for($membership->organization)->create();

        return [$membership, $survey];
    }
}
