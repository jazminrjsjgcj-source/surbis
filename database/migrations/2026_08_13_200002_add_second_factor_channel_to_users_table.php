<?php

declare(strict_types=1);

use App\Domain\Identity\SecondFactorChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Nullable: null significa "sin segundo factor". mfa_confirmed_at
            // dice CUANDO se activo; esta columna dice POR DONDE llega.
            $table->enum('mfa_channel', SecondFactorChannel::values())
                ->nullable()
                ->after('mfa_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('mfa_channel');
        });
    }
};
