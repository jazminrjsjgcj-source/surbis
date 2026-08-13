<?php

declare(strict_types=1);

use App\Domain\Organizations\Enums\BranchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            // restrictOnDelete y no cascade: RF-GEN-010 prohibe el borrado
            // fisico de entidades con historial. Que la base lo impida es
            // mejor que confiar en que nadie escriba el delete.
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->string('code');
            $table->enum('status', BranchStatus::values())
                ->default(BranchStatus::Active->value);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            // Por organizacion, NO global. RNF-AO-BRA-002.
            // Dos ayuntamientos distintos pueden tener una sucursal "CENTRO".
            $table->unique(['organization_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
