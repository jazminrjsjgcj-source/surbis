<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invalidar una respuesta. RF-AO-RES-006 · RNF-AO-RES-002.
     *
     * Se MARCA, nunca se borra: RF-AO-RES-005 prohibe editar las respuestas
     * originales desde el panel, y borrar es la edicion mas destructiva de
     * todas.
     *
     * Columnas aqui y no en una tabla de clasificaciones porque invalidar es
     * UNA decision por respuesta. Sentimiento y confiabilidad —que si son
     * varias y cambian— iran a su propia tabla en la Fase 12.
     */
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table): void {
            $table->timestampTz('invalidated_at')->nullable()->after('submitted_at');

            /*
             * Quien invalido. nullOnDelete: si esa persona se va de la
             * organizacion, la invalidacion sigue en pie —lo que se decidio
             * no cambia porque quien lo decidio ya no este—.
             */
            $table->foreignId('invalidated_by')->nullable()->after('invalidated_at')
                ->constrained('users')->nullOnDelete();

            /*
             * El motivo es obligatorio cuando hay invalidacion.
             *
             * "Invalidada" sin motivo no se puede revisar despues: nadie sabe
             * si fue una prueba, un duplicado o una manipulacion. La regla la
             * aplica InvalidateResponse; la columna es nullable porque la
             * mayoria de respuestas no estan invalidadas.
             */
            $table->text('invalidation_reason')->nullable()->after('invalidated_by');

            // Para excluirlas de los indicadores sin recorrer la tabla.
            $table->index(['organization_id', 'invalidated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'invalidated_at']);
            $table->dropConstrainedForeignId('invalidated_by');
            $table->dropColumn(['invalidated_at', 'invalidation_reason']);
        });
    }
};
