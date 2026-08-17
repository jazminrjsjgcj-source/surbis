<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La restriccion de valor unico, aplazada al final de la transaccion.
     *
     * Es la misma solucion que ya tenia `position` y a esta se le olvido.
     *
     * Sin aplazarla, reordenar o reescribir opciones falla: SaveBuilderState
     * crea las nuevas antes de borrar las viejas, y durante ese instante
     * coexisten dos con el mismo valor. Al final de la transaccion ya no, y
     * eso es lo unico que importa.
     *
     * La garantia NO se pierde: sigue siendo imposible que dos opciones de la
     * misma pregunta acaben con el mismo valor.
     */
    public function up(): void
    {
        DB::statement('alter table survey_question_options
            drop constraint survey_question_options_value_unique');

        DB::statement('alter table survey_question_options
            add constraint survey_question_options_value_unique
            unique (survey_question_id, value)
            deferrable initially deferred');
    }

    public function down(): void
    {
        DB::statement('alter table survey_question_options
            drop constraint survey_question_options_value_unique');

        DB::statement('alter table survey_question_options
            add constraint survey_question_options_value_unique
            unique (survey_question_id, value)');
    }
};
