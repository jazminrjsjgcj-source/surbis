<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->ulid('ulid')->unique()->after('id');

            $table->enum('status', UserStatus::values())
                ->default(UserStatus::Active->value)
                ->after('password');

            // El administrador de plataforma no pertenece a ninguna
            // organizacion cliente: es una propiedad del usuario. RA-001.
            $table->boolean('is_platform_admin')->default(false)->after('status');

            // Cifrado en la capa de aplicacion. RNF-AUT-011.
            $table->text('mfa_secret')->nullable();
            $table->timestampTz('mfa_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'ulid',
                'status',
                'is_platform_admin',
                'mfa_secret',
                'mfa_confirmed_at',
            ]);
        });
    }
};
