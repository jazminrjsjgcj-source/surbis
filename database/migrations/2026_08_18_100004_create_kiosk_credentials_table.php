<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La credencial persistente de una tableta vinculada.
     *
     * Decision del area usuaria, 18 ago 2026: la tableta se configura UNA vez
     * con una clave temporal, y despues se mantiene con esta credencial.
     *
     * Son dos cosas distintas a proposito. Revocar una tableta perdida no
     * obliga a reconfigurar las demas, y una clave temporal caducada ya no
     * sirve para vincular otra.
     *
     * Tabla aparte y no columnas en devices porque una tableta puede
     * REVINCULARSE: la credencial vieja se revoca y la nueva convive un
     * momento. Con columnas habria que decidir cual gana.
     */
    public function up(): void
    {
        Schema::create('kiosk_credentials', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            // Solo el hash, como todo lo demas: si la base se filtra, las
            // tabletas vinculadas siguen sin poder suplantarse.
            $table->string('token_hash', 64)->unique();

            /*
             * Un ano, renovable sola mientras se use. Decision del area
             * usuaria: lo que deja de usarse se apaga solo.
             */
            $table->timestampTz('expires_at');

            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();

            /*
             * Quien la vinculo, para poder revisarlo despues.
             *
             * nullOnDelete: si esa persona se va, la tableta sigue
             * funcionando —lo que se configuro no depende de quien siga en
             * la organizacion—.
             */
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();

            $table->index(['device_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_credentials');
    }
};
