<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Autorizacion para consultar identidades capturadas en modo confidencial.
     *
     * No es una columna en memberships y no es un rol: es una concesion con
     * vigencia que se puede revocar y que sobrevive a su vencimiento para
     * poder auditarla. P-005.
     *
     * Tener la concesion no basta: cada lectura del payload cifrado escribe
     * ademas en audit_logs. RNF-AO-RES-004.
     */
    public function up(): void
    {
        Schema::create('confidential_access_grants', function (Blueprint $table): void {
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
            'alter table confidential_access_grants
             add constraint confidential_access_grants_period_check
             check (expires_at > granted_at)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('confidential_access_grants');
    }
};
