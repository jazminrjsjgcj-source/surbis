<?php

declare(strict_types=1);

use App\Domain\Organizations\Enums\AreaStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            // organization_id esta aunque se deduzca del branch. Sin el, el
            // aislamiento depende de que nadie olvide el join. RF-GEN-003.
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->string('code');
            $table->enum('status', AreaStatus::values())
                ->default(AreaStatus::Active->value);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'code']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
