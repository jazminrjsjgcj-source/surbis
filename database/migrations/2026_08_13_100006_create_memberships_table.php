<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Enums\MembershipStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->enum('role', MembershipRole::values());
            $table->enum('status', MembershipStatus::values())
                ->default(MembershipStatus::Active->value);

            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();

            $table->timestampTz('invited_at')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampsTz();

            // Un usuario puede pertenecer a varias organizaciones, pero no
            // dos veces a la misma. RNF-AO-COL-003. Lo impide la base, no el
            // formulario.
            $table->unique(['organization_id', 'user_id']);

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
