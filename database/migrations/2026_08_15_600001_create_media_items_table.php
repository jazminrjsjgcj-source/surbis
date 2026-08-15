<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biblioteca multimedia. RF-AO-MED-001 a 006.
     *
     * Por organizacion, con una excepcion: los recursos de SISTEMA tienen
     * organization_id nulo y estan disponibles para todas.
     *
     * No es una brecha en el aislamiento: son recursos del PRODUCTO, no datos
     * de nadie. Las caritas de una escala de satisfaccion son las mismas en
     * cualquier ayuntamiento, y obligar a cada organizacion a subirlas seria
     * trabajo repetido sin ninguna ganancia.
     */
    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            /*
             * Nullable SOLO para los recursos de sistema.
             *
             * Es la unica tabla del proyecto donde organization_id puede ser
             * nulo, y por eso lleva esta explicacion: en cualquier otra, un
             * nulo aqui seria una fuga.
             */
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('disk', 32);
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();

            /*
             * Texto alternativo. RNF-GEN-006.
             *
             * Nullable en la base porque los recursos de sistema lo traen
             * puesto y los subidos pueden guardarse antes de escribirlo. Que
             * una imagen SIN texto alternativo no pueda publicarse lo
             * comprobara PublicationChecklist, igual que hace con las
             * etiquetas de opcion.
             */
            $table->string('alt_text')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['organization_id', 'created_at']);
        });

        /*
         * Un archivo no se sube dos veces a la misma organizacion.
         *
         * El path lleva un hash del contenido, asi que subir la misma imagen
         * dos veces produciria dos filas apuntando al mismo archivo: borrar
         * una dejaria a la otra sin fichero.
         */
        DB::statement(
            'create unique index media_items_org_path_unique
             on media_items (coalesce(organization_id, 0), path)
             where deleted_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};
