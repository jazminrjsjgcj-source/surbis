<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\MediaPolicy;
use App\Application\Media\StoreMediaItem;
use App\Domain\Identity\Models\Membership;
use App\Domain\Media\Models\MediaItem;
use Database\Seeders\SystemMediaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Biblioteca multimedia. RF-AO-MED-001 a 006 · RNF-AO-MED-001 y 002.
 */
final class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_imagen_se_guarda_con_su_organizacion(): void
    {
        Storage::fake('local');
        $membership = Membership::factory()->create();

        $item = app(StoreMediaItem::class)->execute(
            $membership->organization,
            $membership->user,
            UploadedFile::fake()->image('foto.jpg', 200, 150),
            'Una foto de prueba',
        );

        $this->assertSame($membership->organization_id, $item->organization_id);
        $this->assertSame('Una foto de prueba', $item->alt_text);
        $this->assertSame(200, $item->width);
    }

    public function test_el_nombre_del_archivo_no_decide_la_ruta(): void
    {
        /*
         * Lo que llega del navegador puede contener rutas o caracteres que en
         * Linux significan otra cosa. Se conserva como etiqueta, pero el
         * archivo se guarda con un nombre que generamos nosotros.
         */
        Storage::fake('local');
        $membership = Membership::factory()->create();

        $item = app(StoreMediaItem::class)->execute(
            $membership->organization,
            $membership->user,
            UploadedFile::fake()->image('../../etc/passwd.jpg'),
        );

        $this->assertStringNotContainsString('..', $item->path);
        $this->assertStringStartsWith("media/{$membership->organization_id}/", $item->path);
    }

    public function test_la_misma_imagen_dos_veces_no_duplica(): void
    {
        // Dos filas apuntando al mismo archivo harian que borrar una dejara a
        // la otra sin fichero.
        Storage::fake('local');
        $membership = Membership::factory()->create();
        $archivo = UploadedFile::fake()->image('igual.png');

        $primera = app(StoreMediaItem::class)->execute(
            $membership->organization, $membership->user, $archivo
        );
        $segunda = app(StoreMediaItem::class)->execute(
            $membership->organization, $membership->user, $archivo
        );

        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame(1, MediaItem::query()->count());
    }

    public function test_svg_no_esta_entre_los_tipos_admitidos(): void
    {
        /*
         * RNF-AO-MED-002. Un SVG puede contener JavaScript, y una imagen que
         * se muestra en el quiosco a ciudadanos no puede ejecutar codigo.
         *
         * Los SVG del juego de SISTEMA si lo son, y no es contradiccion: los
         * escribimos nosotros y van en el repositorio.
         */
        $this->assertNotContains('image/svg+xml', MediaPolicy::MIME_TYPES);
        $this->assertNotContains('svg', MediaPolicy::EXTENSIONS);
    }

    public function test_las_caritas_del_sistema_no_pertenecen_a_nadie(): void
    {
        Storage::fake('local');
        $this->seed(SystemMediaSeeder::class);

        $caritas = MediaItem::query()->system()->get();

        $this->assertCount(5, $caritas);

        foreach ($caritas as $carita) {
            $this->assertNull($carita->organization_id);
            $this->assertTrue($carita->isSystem());

            // Una carita sin nombre no se puede elegir con lector de
            // pantalla, y estas las veran los ciudadanos.
            $this->assertNotEmpty($carita->alt_text);
        }
    }

    public function test_una_organizacion_ve_lo_suyo_y_lo_del_sistema(): void
    {
        Storage::fake('local');
        $this->seed(SystemMediaSeeder::class);

        $membership = Membership::factory()->create();
        $ajena = Membership::factory()->create();

        app(StoreMediaItem::class)->execute(
            $membership->organization, $membership->user, UploadedFile::fake()->image('propia.jpg')
        );
        app(StoreMediaItem::class)->execute(
            $ajena->organization, $ajena->user, UploadedFile::fake()->image('ajena.jpg')
        );

        $visibles = MediaItem::query()->usableBy($membership->organization_id)->get();

        // Las cinco del sistema mas la propia. La ajena NO.
        $this->assertCount(6, $visibles);
        $this->assertFalse($visibles->contains('organization_id', $ajena->organization_id));
    }

    public function test_sembrar_dos_veces_no_duplica_las_caritas(): void
    {
        // updateOrCreate: si el diseno definitivo sustituye un SVG, volver a
        // sembrar lo actualiza sin perder las referencias que ya existan.
        Storage::fake('local');

        $this->seed(SystemMediaSeeder::class);
        $this->seed(SystemMediaSeeder::class);

        $this->assertSame(5, MediaItem::query()->system()->count());
    }

    public function test_el_nombre_accesible_cae_al_nombre_del_archivo(): void
    {
        Storage::fake('local');
        $membership = Membership::factory()->create();

        $item = app(StoreMediaItem::class)->execute(
            $membership->organization, $membership->user, UploadedFile::fake()->image('cartel.jpg')
        );

        // Peor que un texto escrito a mano, mucho mejor que "imagen".
        $this->assertSame('cartel.jpg', $item->accessibleName());
    }
}
