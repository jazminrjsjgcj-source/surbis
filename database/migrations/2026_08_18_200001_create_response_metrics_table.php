<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indicadores precalculados. Decision del area usuaria, 18 ago 2026.
     *
     * Las RESPUESTAS siguen siendo la fuente oficial: esto es un resumen que
     * se puede reconstruir entero en cualquier momento. Si algun dia los dos
     * numeros no coinciden, gana `responses` y esta tabla se rehace.
     *
     * Se precalcula porque agregar cien mil respuestas en cada carga del
     * panel es lento, y un panel lento se deja de mirar.
     *
     * El grano es DIA + deployment + sucursal + area + persona evaluada. Mas
     * fino —por hora— multiplicaria las filas sin que nadie lo pida; mas
     * grueso impediria comparar dos semanas concretas.
     */
    public function up(): void
    {
        Schema::create('response_metrics', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            /*
             * La fecha, en la zona horaria de la ORGANIZACION.
             *
             * Guardar el dia en UTC haria que una respuesta de las 23:30 en
             * Mexico contara como del dia siguiente, y los informes diarios
             * no cuadrarian con lo que vio quien estuvo alli.
             */
            $table->date('day');

            $table->foreignId('deployment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('survey_version_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained()->nullOnDelete();

            $table->string('channel', 32);

            /*
             * Los recuentos.
             *
             * `responses` son las VALIDAS: las invalidadas no cuentan para
             * los indicadores. Pero `invalidated` se guarda al lado porque el
             * panel dice cuantas se excluyeron —un numero que baja sin
             * explicacion genera desconfianza—.
             */
            $table->unsignedInteger('responses')->default(0);
            $table->unsignedInteger('invalidated')->default(0);

            /*
             * La suma, no el promedio.
             *
             * Promediar promedios da resultados falsos: la media de dos dias
             * con 3 y 100 respuestas no es la media de las 103. Se guarda lo
             * que se puede sumar, y el promedio se calcula al agregar.
             */
            $table->unsignedBigInteger('score_sum')->default(0);
            $table->unsignedBigInteger('max_score_sum')->default(0);
            $table->unsignedInteger('scored_responses')->default(0);

            $table->timestampsTz();

            $table->index(['organization_id', 'day']);
            $table->index(['branch_id', 'day']);
            $table->index(['staff_member_id', 'day']);
        });

        /*
         * Una fila por combinacion. Con coalesce porque en PostgreSQL dos
         * NULL no son iguales entre si, y sin esto habria filas duplicadas
         * para todo lo que no tenga sucursal o persona.
         */
        DB::statement(
            'create unique index response_metrics_grain_unique
             on response_metrics (
                 organization_id, day, channel,
                 coalesce(deployment_id, 0),
                 coalesce(survey_version_id, 0),
                 coalesce(branch_id, 0),
                 coalesce(area_id, 0),
                 coalesce(staff_member_id, 0)
             )'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('response_metrics');
    }
};
