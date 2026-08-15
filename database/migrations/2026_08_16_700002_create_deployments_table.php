<?php

declare(strict_types=1);

use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Deployments\Enums\DeploymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aplicaciones de una encuesta. RF-AO-DEP-001 a 010.
     *
     * Un deployment dice DONDE y COMO se aplica una version publicada. NO
     * dice a quien se evalua: eso es de kiosk_session (Fase 8), y mezclarlo
     * aqui ataria una persona a una ubicacion cuando en realidad los turnos
     * cambian varias veces al dia.
     *
     * Una version publicada puede tener VARIOS deployments independientes.
     */
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            /*
             * restrictOnDelete: una version con deployments no se borra.
             *
             * Y solo se despliegan versiones PUBLICADAS, lo que la base no
             * puede comprobar sola —depende del estado de otra fila— asi que
             * lo hace CreateDeployment.
             */
            $table->foreignId('survey_version_id')->constrained()->restrictOnDelete();

            $table->enum('channel', DeploymentChannel::values());
            $table->enum('scope', DeploymentScope::values());

            /*
             * UN SOLO alcance. Las cuatro columnas son nullable y solo una
             * puede tener valor, segun scope.
             *
             * La restriccion esta abajo, en SQL: sin ella se podria guardar
             * un deployment con sucursal Y dispositivo, y nadie sabria cual
             * manda al leerlo.
             */
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->restrictOnDelete();

            $table->enum('status', DeploymentStatus::values())
                ->default(DeploymentStatus::Active->value);

            /*
             * Vigencia, los dos extremos opcionales. Decision del area
             * usuaria: sin fin es indefinido, sin inicio es desde ya.
             *
             * Obligar a poner fecha de fin en un quiosco permanente seria
             * inventarse una.
             */
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();

            /*
             * Token publico. RNF-AO-DEP-002.
             *
             * Se guarda el HASH, no el token. Si la base se filtra, los
             * enlaces publicados siguen sin poder deducirse. El token solo
             * existe en claro en el momento de crearlo y en el QR impreso.
             */
            $table->string('public_token_hash', 64)->nullable()->unique();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('closed_at')->nullable();

            $table->timestampsTz();

            $table->index(['organization_id', 'status']);
            $table->index(['survey_version_id']);
        });

        /*
         * Un solo alcance, y el que corresponde al scope declarado.
         *
         * En la base y no solo en PHP: un deployment mal formado aqui no da
         * error visible, simplemente aplica en un sitio distinto del que
         * alguien creia. Y eso se descubre leyendo respuestas que salieron de
         * donde no debian.
         */
        DB::statement(
            "alter table deployments
             add constraint deployments_single_scope check (
                 (scope = 'organization' and branch_id is null and area_id is null and device_id is null)
              or (scope = 'branch'       and branch_id is not null and area_id is null and device_id is null)
              or (scope = 'area'         and area_id is not null and branch_id is null and device_id is null)
              or (scope = 'device'       and device_id is not null and branch_id is null and area_id is null)
             )"
        );

        /*
         * El quiosco exige dispositivo. Decision del area usuaria.
         *
         * Podria comprobarse solo en PHP, pero entonces una importacion o la
         * API futura podrian saltarselo.
         */
        DB::statement(
            "alter table deployments
             add constraint deployments_kiosk_needs_device check (
                 channel <> 'kiosk' or device_id is not null
             )"
        );

        /*
         * RNF-AO-DEP-001: el inicio no puede ser posterior al fin.
         *
         * Con los dos nulos o uno solo, la comprobacion pasa: son casos
         * validos —indefinido, desde ya—.
         */
        DB::statement(
            'alter table deployments
             add constraint deployments_dates_ordered check (
                 starts_at is null or ends_at is null or starts_at <= ends_at
             )'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
