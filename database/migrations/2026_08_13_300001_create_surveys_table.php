<?php

declare(strict_types=1);

use App\Domain\Surveys\Enums\SurveyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La encuesta es el contenedor estable. NO guarda preguntas.
     *
     * Las respuestas historicas apuntan a una VERSION, no aqui: asi la
     * encuesta puede evolucionar sin que lo ya contestado cambie de
     * significado. RNF-DAT-009.
     */
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->enum('status', SurveyStatus::values())
                ->default(SurveyStatus::Draft->value);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
