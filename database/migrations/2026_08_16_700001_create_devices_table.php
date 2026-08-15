<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dispositivos. Entidad MINIMA, decision del area usuaria 16 ago 2026.
     *
     * El modulo completo es de la Fase 11 (RF-AO-BRA-003): historial de
     * sesiones, resultados por dispositivo, estado de conexion. Aqui solo
     * existe lo que la Fase 6 necesita para que un deployment de quiosco
     * pueda exigir un dispositivo.
     *
     * Se adelanta a proposito: sin esta tabla el canal quiosco no se podria
     * desplegar, y eso bloquearia las Fases 7 a 10, que son el corazon del
     * producto.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            /*
             * La sucursal es obligatoria.
             *
             * Un dispositivo esta fisicamente en un sitio: una tableta no
             * flota en la organizacion. Y de aqui sale la ubicacion que las
             * respuestas guardaran en su fotografia historica.
             */
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();

            // El area es opcional: hay ventanillas sin area definida.
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');

            /*
             * Codigo para identificarlo en la sucursal: "Ventanilla 3".
             *
             * No es la clave de estacion —esa llega en la Fase 8 con
             * TASK-005, y tiene reglas propias de revocacion—. Este es el
             * nombre corto que usa el personal.
             */
            $table->string('code', 40);

            $table->string('status', 20)->default('active');
            $table->timestampsTz();

            $table->index(['organization_id', 'branch_id']);
        });

        // Un codigo no se repite dentro de la misma sucursal. Dos
        // "Ventanilla 3" en la misma sede serian indistinguibles al leer una
        // respuesta.
        DB::statement(
            'alter table devices
             add constraint devices_branch_code_unique
             unique (branch_id, code)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
