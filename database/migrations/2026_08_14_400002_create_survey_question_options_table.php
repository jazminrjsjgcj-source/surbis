<?php

declare(strict_types=1);

use App\Domain\Surveys\Enums\OptionDisplay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las opciones son filas, no JSON dentro de la pregunta.
     *
     * Decidido en TASK-004 y sigue valiendo: como filas, la base puede
     * garantizar que no haya valores duplicados (RF-AO-BLD-010) y que las
     * posiciones sean unicas. Dentro de un JSON, esas dos reglas tendrian que
     * comprobarse en PHP y se romperian el dia que alguien escriba por otra
     * via.
     */
    public function up(): void
    {
        Schema::create('survey_question_options', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('survey_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            /*
             * La etiqueta se guarda SIEMPRE, incluso cuando la opcion se
             * muestra solo como imagen. Es el nombre accesible que pide
             * RF-AO-BLD-005: una carita sin nombre no se puede elegir con
             * lector de pantalla.
             */
            $table->string('label');

            // Lo que queda guardado en la respuesta. Estable aunque la
            // etiqueta se reescriba.
            $table->string('value');

            // Puntuacion. Nullable porque no todos los tipos puntuan.
            $table->smallInteger('score')->nullable();

            $table->enum('display', OptionDisplay::values())
                ->default(OptionDisplay::Text->value);

            /*
             * media_id llega en la Fase 5, cuando exista la biblioteca.
             * RF-AO-BLD-004 no se puede cumplir hasta entonces: la columna se
             * anade con su clave foranea en esa fase, no ahora sin destino.
             */

            // Fondo, borde, estado seleccionado y forma. RF-AO-BLD-006.
            // El ajuste y el tamano de imagen llegan con la Fase 5.
            $table->jsonb('appearance')->nullable();

            $table->unsignedInteger('position');

            $table->timestampsTz();

            $table->index(['survey_question_id', 'position']);
        });

        /*
         * RF-AO-BLD-010: sin valores duplicados dentro de la misma pregunta.
         *
         * En la base y no solo en la validacion: dos opciones con el mismo
         * valor harian que las respuestas fueran indistinguibles, y eso no se
         * puede reparar despues.
         */
        DB::statement(
            'alter table survey_question_options
             add constraint survey_question_options_value_unique
             unique (survey_question_id, value)'
        );

        // Diferible, por el mismo motivo que en las preguntas: reordenar pasa
        // por un estado intermedio con posiciones repetidas.
        DB::statement(
            'alter table survey_question_options
             add constraint survey_question_options_position_unique
             unique (survey_question_id, position)
             deferrable initially deferred'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_question_options');
    }
};
