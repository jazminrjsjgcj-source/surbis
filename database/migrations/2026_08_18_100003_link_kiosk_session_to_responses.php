<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La clave foranea que faltaba.
     *
     * `responses.kiosk_session_id` existe desde la Fase 9, sin restriccion,
     * porque la tabla no existia todavia. Queda escrito en aquella migracion:
     * "se anade la clave foranea cuando exista".
     */
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table): void {
            // restrictOnDelete: una respuesta explica de quien era el turno.
            $table->foreign('kiosk_session_id')
                ->references('id')->on('kiosk_sessions')
                ->restrictOnDelete();

            $table->index(['kiosk_session_id']);
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table): void {
            $table->dropForeign(['kiosk_session_id']);
            $table->dropIndex(['kiosk_session_id']);
        });
    }
};
