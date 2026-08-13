<?php

declare(strict_types=1);

use App\Domain\Organizations\Enums\StaffMemberStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La persona a la que se evalua. NO es lo mismo que una membresia:
     *
     *   membership     una cuenta que inicia sesion y opera el sistema
     *   staff_member   una persona a la que se evalua, tenga cuenta o no
     *
     * Quien atiende una ventanilla puede no tener usuario. Quien abre la
     * estacion de quiosco si lo tiene por fuerza, porque inicia sesion.
     * Pueden ser la misma persona o no. P-007.
     */
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            // Opcional y unico: una cuenta se vincula como mucho a una
            // persona evaluable.
            $table->foreignId('membership_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('employee_code')->nullable();

            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('status', StaffMemberStatus::values())
                ->default(StaffMemberStatus::Active->value);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'status']);
        });

        // Unico indice parcial del paquete, y por eso va en SQL: el
        // constructor de esquema de Laravel no expresa `where`. Sin el, un
        // unique normal serviria igual en PostgreSQL —trata cada NULL como
        // distinto— pero no dejaria escrito que el codigo de empleado es
        // opcional, y el siguiente que lo lea tendria que deducirlo.
        DB::statement(
            'create unique index staff_members_org_employee_code_unique
             on staff_members (organization_id, employee_code)
             where employee_code is not null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_members');
    }
};
