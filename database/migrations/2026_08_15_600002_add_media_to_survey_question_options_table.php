<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La imagen de una opcion. RF-AO-BLD-004.
     *
     * La columna se anade AHORA y no en TASK-019 a proposito: entonces no
     * existia la biblioteca, y una clave foranea sin destino habria sido una
     * columna que nadie podia rellenar.
     */
    public function up(): void
    {
        Schema::table('survey_question_options', function (Blueprint $table): void {
            /*
             * restrictOnDelete y NO cascade.
             *
             * Borrar una imagen de la biblioteca no puede vaciar en silencio
             * las opciones que la usan: una encuesta publicada pasaria a
             * mostrar huecos sin que nadie lo supiera.
             *
             * MediaItem usa borrado suave, asi que en la practica una imagen
             * en uso se archiva y su fichero sigue ahi. Esta restriccion es
             * la red por si alguien borra de verdad.
             */
            $table->foreignId('media_id')
                ->nullable()
                ->after('display')
                ->constrained('media_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('survey_question_options', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('media_id');
        });
    }
};
