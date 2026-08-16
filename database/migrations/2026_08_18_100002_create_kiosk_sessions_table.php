<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quien esta siendo evaluado AHORA en un dispositivo. RF-COL-001 a 006.
     *
     * El deployment dice DONDE y COMO se aplica la encuesta; la sesion dice A
     * QUIEN se evalua (D-035). Separarlos es lo que permite que el turno
     * cambie tres veces al dia sin tocar la configuracion.
     */
    public function up(): void
    {
        Schema::create('kiosk_sessions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->foreignId('deployment_id')->constrained()->restrictOnDelete();

            /*
             * La persona evaluada.
             *
             * Nullable: hay quioscos que miden el servicio de una ventanilla
             * sin atribuirlo a nadie en concreto. Obligarlo forzaria a
             * inventar una persona.
             */
            $table->foreignId('staff_member_id')->nullable()->constrained()->restrictOnDelete();

            /*
             * Quien abrio la sesion.
             *
             * Es el colaborador que prepara la estacion, y NO tiene por que
             * ser la persona evaluada: en una oficina puede abrirla el
             * responsable del turno.
             */
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampTz('started_at');
            $table->timestampTz('last_activity_at');
            $table->timestampTz('closed_at')->nullable();

            /*
             * Por que se cerro.
             *
             * 'replaced' cuando otra sesion la sustituye al cambiar el turno,
             * 'manual' cuando alguien la cierra, 'expired' por inactividad
             * larga. Sin esto, una sesion cerrada no explica nada.
             */
            $table->string('closed_reason', 20)->nullable();

            $table->timestampsTz();

            $table->index(['organization_id', 'started_at']);
            $table->index(['staff_member_id', 'started_at']);
        });

        /*
         * UNA sola sesion activa por dispositivo. Decision del area usuaria.
         *
         * En la base y no solo en PHP: dos sesiones abiertas en la misma
         * tableta harian que las respuestas se atribuyeran a cualquiera de
         * las dos, y el fallo no daria error —solo datos mal repartidos que
         * se descubren mucho despues—.
         */
        DB::statement(
            'create unique index kiosk_sessions_one_active_per_device
             on kiosk_sessions (device_id)
             where closed_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_sessions');
    }
};
