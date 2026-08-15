<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloqueo optimista del borrador. Decision del area usuaria, 14 ago 2026.
     *
     * Un entero y no updated_at: dos guardados en el mismo milisegundo
     * pasarian como si nada con una marca de tiempo, y la precision del reloj
     * dejaria de ser un detalle para convertirse en una regla de negocio.
     *
     * Protege la VERSION entera, no cada pregunta. El borrador es la unidad
     * que se publica, asi que dos personas editando preguntas distintas
     * tambien chocan. Con dos o tres administradores por organizacion es
     * aceptable; con veinte no lo seria, y entonces habria que bajar el
     * bloqueo a la pregunta.
     */
    public function up(): void
    {
        Schema::table('survey_versions', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('survey_versions', function (Blueprint $table): void {
            $table->dropColumn('lock_version');
        });
    }
};
