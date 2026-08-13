<?php

declare(strict_types=1);

use App\Domain\Identity\SecondFactorChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El codigo pendiente de verificar.
     *
     * Tabla propia y no columnas en users por dos razones: un usuario puede
     * pedir un codigo nuevo antes de usar el anterior, y al terminar la
     * verificacion la fila se borra, mientras que users no debe llenarse de
     * campos que casi siempre estan vacios.
     *
     * Nunca guarda el codigo: solo su hash. RNF-AUT-012.
     */
    public function up(): void
    {
        Schema::create('second_factor_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', SecondFactorChannel::values());
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('second_factor_challenges');
    }
};
