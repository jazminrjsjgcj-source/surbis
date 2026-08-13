<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureDates();
        $this->configureDatabase();
        $this->configureUrls();
    }

    /**
     * Convierte en excepción la carga perezosa de relaciones, la asignación de
     * atributos inexistentes y el acceso a atributos no cargados.
     *
     * Fuera de producción, para que un N+1 rompa la suite de pruebas en lugar
     * de llegar a producción como lentitud silenciosa.
     *
     * RNF-GEN-010
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Todas las fechas del sistema son inmutables. Un `->addDay()` sobre una
     * fecha compartida deja de poder modificarla a distancia.
     *
     * RNF-GEN-013
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Bloquea en producción los comandos destructivos de base de datos:
     * db:wipe, migrate:fresh, migrate:refresh y migrate:reset.
     *
     * ANEXO 1 seccion 28
     */
    private function configureDatabase(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    /**
     * Fuerza HTTPS en las URLs generadas cuando la aplicacion corre en
     * produccion detras de Nginx.
     *
     * RNF-GEN-003
     */
    private function configureUrls(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
