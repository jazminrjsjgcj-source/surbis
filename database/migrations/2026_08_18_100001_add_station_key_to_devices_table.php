<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La clave de estacion. TASK-005, que espera desde la Fase 2.
     *
     * Una tableta de ventanilla NO tiene usuario: no hay nadie que escriba
     * un correo y una contrasena cada manana. Se identifica con una clave que
     * el administrador genera y puede revocar.
     *
     * Se guarda el HASH, igual que los tokens publicos: si la base se filtra,
     * las tabletas ya configuradas siguen sin poder suplantarse.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('station_key_hash', 64)->nullable()->unique()->after('code');

            /*
             * Cuando se genero la clave actual.
             *
             * Sirve para dos cosas: saber cuanto lleva sin rotarse, y
             * comprobar que una sesion abierta antes de una revocacion ya no
             * vale.
             */
            $table->timestampTz('station_key_set_at')->nullable()->after('station_key_hash');

            /*
             * Revocar NO borra el hash: lo marca.
             *
             * Borrarlo sin mas dejaria un dispositivo indistinguible de uno
             * que nunca se configuro, y nadie sabria si la clave se retiro a
             * proposito o se perdio.
             */
            $table->timestampTz('station_key_revoked_at')->nullable()->after('station_key_set_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn(['station_key_hash', 'station_key_set_at', 'station_key_revoked_at']);
        });
    }
};
