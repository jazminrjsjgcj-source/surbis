<?php

declare(strict_types=1);

use App\Domain\Surveys\Enums\QuestionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las preguntas cuelgan de una VERSION, no de la encuesta.
     *
     * Es lo que hace que publicar congele el contenido: una respuesta apunta a
     * su version, y esa version tiene las preguntas tal y como estaban. Si
     * colgaran de la encuesta, editar una pregunta cambiaria el significado de
     * lo ya contestado. RF-AO-SUR-007 y RNF-DAT-009.
     */
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('survey_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            $table->enum('type', QuestionType::values());
            $table->text('text');
            $table->text('help')->nullable();
            $table->boolean('is_required')->default(false);

            /*
             * Limites por tipo: minimo y maximo de un numero, longitud de un
             * texto, rango de fechas, cuantas opciones admite una seleccion
             * multiple.
             *
             * En jsonb con forma declarada —QuestionLimits— y no en nueve
             * columnas de las que cada tipo usaria dos. Un esquema lleno de
             * columnas siempre nulas no dice que significa cada una.
             */
            $table->jsonb('limits')->nullable();

            $table->unsignedInteger('position');

            $table->timestampsTz();

            $table->index(['survey_version_id', 'position']);
            $table->index(['organization_id']);
        });

        /*
         * Posiciones unicas dentro de una version. RNF-AO-BLD-002.
         *
         * DEFERRABLE porque reordenar intercambia posiciones, y a mitad de la
         * operacion dos preguntas ocupan el mismo numero. Sin diferir, la base
         * rechazaria el paso intermedio de una operacion valida y reordenar
         * seria imposible sin trucos.
         *
         * La comprobacion ocurre igualmente: al cerrar la transaccion.
         */
        DB::statement(
            'alter table survey_questions
             add constraint survey_questions_position_unique
             unique (survey_version_id, position)
             deferrable initially deferred'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
