<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Logica condicional. RF-AO-BLD-007.
     *
     * UNA condicion por pregunta, basada en UNA opcion de una pregunta
     * ANTERIOR. Decision del area usuaria, 15 ago 2026: condiciones
     * multiples, operadores y saltos quedan fuera de esta fase.
     *
     * Los ciclos son imposibles POR CONSTRUCCION, no por deteccion: si la
     * pregunta origen tiene que ir antes, no puede haber un camino de vuelta.
     * Escribir un detector de ciclos que nunca puede encontrar uno seria un
     * mecanismo que no hace nada (ANEXO 1 seccion 92).
     */
    public function up(): void
    {
        Schema::create('survey_question_conditions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            /*
             * Una sola condicion por pregunta: la clave foranea es UNIQUE.
             *
             * En la base y no solo en la validacion. Si la regla viviera solo
             * en PHP, una segunda via de escritura —la API, una importacion—
             * podria crear dos condiciones y la pregunta pasaria a depender
             * de dos cosas sin que nadie lo decidiera.
             */
            $table->foreignId('survey_question_id')->unique()->constrained()->cascadeOnDelete();

            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            /*
             * restrictOnDelete y NO cascade.
             *
             * Borrar la pregunta origen dejaria la condicion sin referencia.
             * La base lo impide, y SaveBuilderState avisa antes con el nombre
             * de las preguntas que dependen. Es la misma regla de D-017: se
             * archiva de dentro hacia fuera, sin cascada silenciosa.
             */
            $table->foreignId('depends_on_question_id')
                ->constrained('survey_questions')
                ->restrictOnDelete();

            $table->foreignId('option_id')
                ->constrained('survey_question_options')
                ->cascadeOnDelete();

            $table->timestampsTz();

            $table->index(['organization_id']);
            $table->index(['depends_on_question_id']);
        });

        /*
         * Una pregunta no puede depender de si misma.
         *
         * Es el unico ciclo posible con una sola condicion, y la base lo
         * impide. Con la regla de "solo hacia atras" tampoco podria ocurrir,
         * pero esta comprobacion no depende de que las posiciones esten bien.
         */
        DB::statement(
            'alter table survey_question_conditions
             add constraint survey_question_conditions_not_self
             check (survey_question_id <> depends_on_question_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_question_conditions');
    }
};
