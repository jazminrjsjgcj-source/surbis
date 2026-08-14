<?php

declare(strict_types=1);

use App\Domain\Surveys\Enums\SurveyVersionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_versions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('survey_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('version_number');

            $table->enum('status', SurveyVersionStatus::values())
                ->default(SurveyVersionStatus::Draft->value);

            // Introduccion, agradecimiento, modo de identidad, navegacion,
            // inactividad, comentario y ayuda. RF-AO-SUR-005 y RF-AO-PUB-001.
            $table->jsonb('settings')->nullable();

            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            // RNF-AO-PUB-001: unico por encuesta.
            $table->unique(['survey_id', 'version_number']);

            $table->index(['organization_id', 'status']);
        });

        /*
         * Un unico borrador vivo por encuesta.
         *
         * Es la traduccion a PostgreSQL de RF-AO-SUR-007: los cambios
         * posteriores a una publicacion van a un borrador nuevo, no a otro
         * mas. Sin este indice, dos pestanas abiertas crean dos borradores y
         * nadie se entera hasta que uno de los dos se pierde al publicar.
         *
         * Va en SQL porque el constructor de esquema de Laravel no expresa
         * `where` en un indice.
         */
        DB::statement(
            "create unique index survey_versions_single_draft_unique
             on survey_versions (survey_id)
             where status = 'draft'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_versions');
    }
};
