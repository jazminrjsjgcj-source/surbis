<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\StoreMediaItem;
use App\Domain\Identity\Models\Membership;
use App\Domain\Media\Models\MediaItem;
use Database\Seeders\SystemMediaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Quien puede ver que imagen. RNF-GEN-005.
 *
 * Dos origenes, dos tratos:
 *
 *   sistema      disco publico, sin comprobacion. Son del producto.
 *   subidas      disco privado, servidas tras comprobar la organizacion.
 *
 * La distincion importa: publicar el disco entero dejaria las fotos de una
 * organizacion accesibles con adivinar la ruta, y el hash del nombre no es
 * una proteccion —es un identificador, no un secreto—.
 */
final class MediaAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_del_sistema_van_al_disco_publico(): void
    {
        Storage::fake('public');
        $this->seed(SystemMediaSeeder::class);

        foreach (MediaItem::query()->system()->get() as $carita) {
            $this->assertSame('public', $carita->disk);
            $this->assertTrue(Storage::disk('public')->exists($carita->path));
        }
    }

    public function test_las_subidas_van_al_disco_privado(): void
    {
        Storage::fake('local');
        $membership = Membership::factory()->create();

        $item = app(StoreMediaItem::class)->execute(
            $membership->organization,
            $membership->user,
            UploadedFile::fake()->image('privada.jpg'),
        );

        $this->assertSame('local', $item->disk);
    }

    public function test_una_imagen_ajena_no_se_sirve(): void
    {
        /*
         * LA PRUEBA QUE JUSTIFICA TODO ESTO.
         *
         * Los ULID viajan al navegador. Sin comprobar la organizacion,
         * bastaria con pedir el de otra para ver sus fotos.
         */
        Storage::fake('local');

        $ajena = Membership::factory()->create();
        $item = app(StoreMediaItem::class)->execute(
            $ajena->organization, $ajena->user, UploadedFile::fake()->image('ajena.jpg'),
        );

        $this->admin();

        $this->get(route('media.show', $item->ulid))->assertForbidden();
    }

    public function test_la_imagen_propia_si_se_sirve(): void
    {
        Storage::fake('local');
        $membership = $this->admin();

        $item = app(StoreMediaItem::class)->execute(
            $membership->organization,
            $membership->user,
            UploadedFile::fake()->image('propia.jpg'),
        );

        $this->get(route('media.show', $item->ulid))->assertOk();
    }

    public function test_la_url_de_una_del_sistema_no_pasa_por_el_controlador(): void
    {
        /*
         * Van directas desde el disco publico: una peticion a PHP por cada
         * carita de cada pregunta seria un coste sin ninguna ganancia.
         */
        Storage::fake('public');
        $this->seed(SystemMediaSeeder::class);

        $carita = MediaItem::query()->system()->first();

        /*
         * Se compara con la ruta del CONTROLADOR, no con la cadena "/media/".
         *
         * Esa palabra aparece tambien en la ruta del disco publico
         * —media/system/smiley/bien.svg— asi que buscarla no distinguia una
         * cosa de la otra.
         */
        $this->assertNotSame(route('media.show', $carita->ulid), $carita->url());
        $this->assertStringContainsString('/storage/', $carita->url());
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
