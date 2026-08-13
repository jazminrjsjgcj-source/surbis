<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\PasswordPolicy;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Policies\AreaPolicy;
use App\Policies\BranchPolicy;
use App\Policies\MembershipPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configurePasswords();
        $this->configurePolicies();
        $this->configurePagination();
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
     * La paginacion usa la plantilla del proyecto y no la de Laravel.
     *
     * La del framework trae utilidades de Tailwind escritas a mano, con
     * colores fuera de nuestros tokens y direcciones fijas que romperian en
     * arabe. Adaptarla cuesta mas que escribirla.
     */
    private function configurePagination(): void
    {
        Paginator::defaultView('vendor.pagination.default');
        Paginator::defaultSimpleView('vendor.pagination.default');
    }

    /**
     * Laravel descubre las Policies por convencion: busca App\Policies\XPolicy
     * para App\Models\X. Los modelos de este proyecto viven en
     * App\Domain\...\Models, asi que el descubrimiento no los encuentra y hay
     * que registrarlas a mano.
     *
     * Si se olvida una, `authorize()` lanza AuthorizationException y la accion
     * se deniega: falla en la direccion segura y de forma ruidosa. Aun asi se
     * registra explicitamente, porque un 403 inexplicable cuesta mas de
     * diagnosticar que una linea aqui.
     */
    private function configurePolicies(): void
    {
        Gate::policy(Area::class, AreaPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Membership::class, MembershipPolicy::class);
    }

    /**
     * La politica de contrasenas del sistema se aplica en todas partes por
     * defecto, no regla a regla en cada formulario. RF-AUT-012.
     */
    private function configurePasswords(): void
    {
        Password::defaults(fn (): Password => PasswordPolicy::rules());
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
