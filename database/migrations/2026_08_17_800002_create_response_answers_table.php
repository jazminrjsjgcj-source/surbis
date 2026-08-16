<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada respuesta a cada pregunta.
     *
     * Tabla aparte y no un JSON dentro de responses: la analitica de la Fase
     * 12 agrupa por pregunta y por opcion, y hacerlo sobre un JSON obliga a
     * recorrerlo entero en cada consulta.
     */
    public function up(): void
    {
        Schema::create('response_answers', function (Blueprint $table): void {
            $table->id();

            // cascadeOnDelete aqui SI: una respuesta a una pregunta no
            // significa nada sin su respuesta completa.
            $table->foreignId('response_id')->constrained()->cascadeOnDelete();

            $table->foreignId('survey_question_id')->constrained()->restrictOnDelete();

            /*
             * La opcion elegida, cuando la hay.
             *
             * Nullable porque un texto libre o un numero no eligen opcion. Y
             * una pregunta de seleccion multiple produce VARIAS filas: una
             * por opcion marcada.
             */
            $table->foreignId('option_id')->nullable()->constrained('survey_question_options')->restrictOnDelete();

            /*
             * SNAPSHOTS de la pregunta y la opcion.
             *
             * El texto de una pregunta puede cambiar en versiones futuras, y
             * una respuesta tiene que seguir diciendo QUE se pregunto.
             */
            $table->text('question_text');
            $table->string('question_type', 32);
            $table->string('option_label')->nullable();

            /*
             * El valor escrito: texto, numero o fecha, como cadena.
             *
             * Una sola columna y no tres: el tipo ya esta en question_type, y
             * tres columnas nullable obligarian a mirar cual tiene valor en
             * cada consulta.
             */
            $table->text('value')->nullable();

            /*
             * La puntuacion de ESTA respuesta, resuelta en el servidor.
             *
             * Se copia de la opcion al guardar. Si se leyera de la opcion al
             * consultar, editar una escala cambiaria retroactivamente todos
             * los resultados historicos.
             */
            $table->smallInteger('score')->nullable();

            $table->unsignedSmallInteger('position');
            $table->timestampsTz();

            $table->index(['survey_question_id']);
            $table->index(['option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_answers');
    }
};
