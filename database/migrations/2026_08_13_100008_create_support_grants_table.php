<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acceso de soporte del administrador de plataforma a una organizacion.
     *
     * RA-001 exige que ese acceso sea explicito, temporal, justificado y
     * auditado. Las cuatro palabras estan en la tabla: la fila es lo
     * explicito, expires_at lo temporal, reason lo justificado, y cada uso
     * escribe en audit_logs.
     *
     * Un permiso vencido no se borra. Saber quien entro y por que en marzo
     * vale mas que tener la tabla limpia.
     */
    public function up(): void
    {
        Schema::create('support_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->text('reason');

            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('granted_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'organization_id', 'expires_at']);
        });

        // Sin vencimiento posterior a la concesion no seria temporal, y
        // RA-001 pide que lo sea. Laravel no expresa un CHECK que compare dos
        // columnas, asi que este si va en SQL.
        DB::statement(
            'alter table support_grants
             add constraint support_grants_period_check
             check (expires_at > granted_at)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('support_grants');
    }
};
