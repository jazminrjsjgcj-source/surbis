<?php

declare(strict_types=1);

use App\Domain\Organizations\Enums\OrganizationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('slug')->unique();

            // Sin valor por defecto a proposito. RNF-GEN-013 obliga a mostrar
            // cada fecha en la zona de su organizacion; una zona puesta por
            // descuido desplaza los informes sin avisar nunca.
            $table->string('timezone');

            // enum() sobre PostgreSQL NO crea un tipo nativo: compila a
            // varchar con CHECK. Es lo que queremos. Un tipo ENUM nativo
            // obliga a ALTER TYPE para anadir un valor y es practicamente
            // irreversible para quitarlo.
            //
            // Los valores salen del enum de PHP, asi que la fuente de verdad
            // es una sola y no puede divergir. RF-GEN-006, RNF-GEN-012.
            $table->enum('status', OrganizationStatus::values())
                ->default(OrganizationStatus::Active->value);

            $table->jsonb('settings')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
