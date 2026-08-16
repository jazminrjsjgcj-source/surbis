<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una respuesta contestada. RF-AO-RES-* · RNF-DAT-009.
     *
     * Lleva REFERENCIAS y SNAPSHOTS a la vez. Decision del area usuaria,
     * 17 ago 2026.
     *
     * Las referencias sirven para consultar y agregar; los snapshots, para
     * que un informe de hace un año siga diciendo la verdad. Si una sucursal
     * pasa de "Palacio Municipal" a "Centro Civico", una respuesta de antes
     * del cambio se dio en el Palacio, y comparar periodos con los nombres
     * cambiando bajo los pies produce informes que mienten sin avisar.
     */
    public function up(): void
    {
        Schema::create('responses', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            /*
             * REFERENCIAS. restrictOnDelete en todas: una respuesta explica de
             * donde salio, y borrar su origen la dejaria huerfana.
             *
             * RF-GEN-010: nada con historial se borra.
             */
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('deployment_id')->constrained()->restrictOnDelete();
            $table->foreignId('survey_version_id')->constrained()->restrictOnDelete();

            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();

            /*
             * La persona evaluada y la sesion.
             *
             * Nullable porque un enlace publico no evalua a nadie en concreto:
             * solo el quiosco tiene turno. La sesion llega en la Fase 8, asi
             * que la columna existe sin su tabla todavia — se anade la clave
             * foranea cuando exista.
             */
            $table->foreignId('staff_member_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('kiosk_session_id')->nullable();

            /*
             * SNAPSHOTS. Lo que se llamaban las cosas CUANDO se contesto.
             *
             * Se guardan aunque exista la referencia: son datos distintos.
             * La referencia dice "esta sucursal"; el snapshot dice "asi se
             * llamaba entonces".
             */
            $table->string('organization_name');
            $table->string('branch_name')->nullable();
            $table->string('area_name')->nullable();
            $table->string('device_name')->nullable();
            $table->string('staff_member_name')->nullable();
            $table->unsignedInteger('survey_version_number');
            $table->string('survey_name');
            $table->string('channel', 32);

            /*
             * La puntuacion, calculada EN EL SERVIDOR. RNF-COL-013.
             *
             * Nullable: una encuesta de solo texto no puntua. Y se guarda ya
             * calculada porque recalcularla mas tarde daria otro numero si
             * alguien edito las opciones de un borrador posterior.
             */
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('max_score')->nullable();

            $table->text('comment')->nullable();

            /*
             * Identidad, cifrada. RNF-COL-014.
             *
             * El texto va cifrado y NO se puede buscar; el indice ciego —un
             * HMAC del valor normalizado— permite busqueda exacta sin
             * descifrar. Quien acceda a la base ve un hash, no el correo.
             *
             * Decision del area usuaria, 17 ago 2026.
             */
            $table->text('respondent_name')->nullable();
            $table->text('respondent_email')->nullable();
            $table->text('respondent_phone')->nullable();
            $table->string('respondent_email_index', 64)->nullable();
            $table->string('respondent_phone_index', 64)->nullable();

            /*
             * El modo de identidad CUANDO se contesto.
             *
             * Otro snapshot: si la encuesta pasa despues a identificada, esta
             * respuesta se dio en anonimo y sigue siendo anonima. Cambiar la
             * configuracion no puede desanonimizar lo ya recogido.
             */
            $table->string('identity_mode', 32);
            $table->timestampTz('consent_given_at')->nullable();

            /*
             * UUID de idempotencia. RNF-AO-RES-* y Fase 10.
             *
             * Sin conexion, el quiosco reintenta el envio. Sin esto, cada
             * reintento crearia una respuesta mas y los resultados saldrian
             * inflados sin que nadie lo notara.
             */
            $table->uuid('idempotency_key')->unique();

            $table->timestampTz('submitted_at');
            $table->timestampsTz();

            $table->index(['organization_id', 'submitted_at']);
            $table->index(['deployment_id', 'submitted_at']);
            $table->index(['branch_id', 'submitted_at']);
            $table->index(['respondent_email_index']);
        });

        /*
         * La puntuacion no puede superar el maximo posible.
         *
         * Una comprobacion barata que caza errores de calculo: si el maximo
         * se calcula con las preguntas visibles y la puntuacion con todas,
         * los numeros no cuadran y aqui salta.
         */
        DB::statement(
            'alter table responses
             add constraint responses_score_within_max check (
                 score is null or max_score is null or score <= max_score
             )'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};
