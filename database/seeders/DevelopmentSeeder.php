<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\MembershipRole;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\AreaStatus;
use App\Domain\Organizations\Enums\BranchStatus;
use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\StaffMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Datos para trabajar en local. NUNCA en produccion.
 *
 * El reparto no es decorativo: esta pensado para que una sola pasada por el
 * sistema muestre los estados vacios, la paginacion, los badges y las filas
 * sin cuenta. Un seeder que crea diez registros iguales deja sin ver
 * justamente lo que cuesta revisar.
 */
final class DevelopmentSeeder extends Seeder
{
    private const DOMAIN = '@example.test';

    public function run(): void
    {
        $this->guardAgainstProduction();

        $password = $this->password();

        $organization = Organization::query()->create([
            'name' => 'Municipio de prueba',
            'slug' => 'municipio-de-prueba',
            'timezone' => 'America/Mazatlan',
            'status' => OrganizationStatus::Active,
        ]);

        $branches = $this->createBranches($organization);
        $this->createAreas($organization, $branches);
        $admin = $this->createAccounts($organization, $branches, $password);
        $this->createPeople($organization, $branches, $password);

        $this->summary($password, $admin);
    }

    /**
     * Un seeder de desarrollo ejecutado en produccion crea cuentas con
     * contrasena conocida en un sistema real. Se comprueba con codigo y no
     * con un comentario que pida cuidado.
     */
    private function guardAgainstProduction(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DevelopmentSeeder no se ejecuta en produccion. '.
                'Crea la organizacion y su primer administrador con un comando dedicado.'
            );
        }
    }

    /**
     * La contrasena no viaja en el codigo, ni siquiera para desarrollo.
     * Sale de la configuracion, y si falta el seeder para y dice que poner.
     *
     * config() y no env(): con la configuracion cacheada, env() devuelve null
     * fuera de los archivos de config, y el seeder fallaria por un motivo que
     * no tiene nada que ver con el seeder.
     */
    private function password(): string
    {
        $password = (string) config('seeding.password', '');

        if ($password === '') {
            throw new RuntimeException(
                'Falta SEED_PASSWORD en tu .env. Anade una linea como:'.PHP_EOL.
                '    SEED_PASSWORD=loquetuquieras'.PHP_EOL.
                'No se pone un valor por defecto a proposito: una contrasena '.
                'escrita en el repositorio acaba en produccion tarde o temprano.'
            );
        }

        return $password;
    }

    /** @return array<string, Branch> */
    private function createBranches(Organization $organization): array
    {
        $centro = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Palacio Municipal',
            'code' => 'CENTRO',
            'status' => BranchStatus::Active,
        ]);

        $norte = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Delegacion Norte',
            'code' => 'NORTE',
            'status' => BranchStatus::Active,
        ]);

        // Una archivada, para ver los dos badges sin tener que archivar nada.
        Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Modulo Provisional',
            'code' => 'PROVISIONAL',
            'status' => BranchStatus::Archived,
            'archived_at' => now()->subMonths(3),
        ]);

        // Y una sin areas, para ver el estado vacio de esa pantalla.
        $sinAreas = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Delegacion Sur',
            'code' => 'SUR',
            'status' => BranchStatus::Active,
        ]);

        return ['centro' => $centro, 'norte' => $norte, 'sin_areas' => $sinAreas];
    }

    /** @param array<string, Branch> $branches */
    private function createAreas(Organization $organization, array $branches): void
    {
        $areas = [
            ['branch' => 'centro', 'name' => 'Ventanilla de predial', 'code' => 'PREDIAL'],
            ['branch' => 'centro', 'name' => 'Ventanilla de licencias', 'code' => 'LICENCIAS'],
            ['branch' => 'centro', 'name' => 'Caja', 'code' => 'CAJA'],
            ['branch' => 'norte', 'name' => 'Atencion ciudadana', 'code' => 'ATENCION'],
            ['branch' => 'norte', 'name' => 'Caja', 'code' => 'CAJA'],
        ];

        foreach ($areas as $area) {
            Area::query()->create([
                'organization_id' => $organization->id,
                'branch_id' => $branches[$area['branch']]->id,
                'name' => $area['name'],
                'code' => $area['code'],
                'status' => AreaStatus::Active,
            ]);
        }
    }

    /** @param array<string, Branch> $branches */
    private function createAccounts(Organization $organization, array $branches, string $password): User
    {
        $admin = $this->user('Ana Administradora', 'admin'.self::DOMAIN, $password);

        Membership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'role' => MembershipRole::Admin,
            'status' => MembershipStatus::Active,
            'joined_at' => now()->subMonths(6),
        ]);

        // Un segundo administrador: sin el, el primero no puede suspender a
        // nadie y la pantalla de personas se ve a medias.
        $segundo = $this->user('Beto Administrador', 'admin2'.self::DOMAIN, $password);

        Membership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $segundo->id,
            'role' => MembershipRole::Admin,
            'status' => MembershipStatus::Active,
            'joined_at' => now()->subMonths(4),
        ]);

        $colaborador = $this->user('Carlos Colaborador', 'colaborador'.self::DOMAIN, $password);

        Membership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $colaborador->id,
            'role' => MembershipRole::Collaborator,
            'status' => MembershipStatus::Active,
            'branch_id' => $branches['centro']->id,
            'joined_at' => now()->subMonths(2),
        ]);

        // Administrador de plataforma: no pertenece a ninguna organizacion.
        $this->user('Paula Plataforma', 'plataforma'.self::DOMAIN, $password, platformAdmin: true);

        return $admin;
    }

    /** @param array<string, Branch> $branches */
    private function createPeople(Organization $organization, array $branches, string $password): void
    {
        $areas = Area::query()->where('organization_id', $organization->id)->get();

        // Una membresia suspendida de verdad y otra que es una invitacion sin
        // usar: en pantalla dicen cosas distintas y hay que poder verlo.
        $suspendida = $this->user('Sofia Suspendida', 'suspendida'.self::DOMAIN, $password);

        Membership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $suspendida->id,
            'role' => MembershipRole::Collaborator,
            'status' => MembershipStatus::Suspended,
            'branch_id' => $branches['norte']->id,
            'joined_at' => now()->subMonths(3),
            'suspended_at' => now()->subWeeks(2),
        ]);

        $invitada = $this->user('Irene Invitada', 'invitada'.self::DOMAIN, $password);

        Membership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $invitada->id,
            'role' => MembershipRole::Collaborator,
            'status' => MembershipStatus::Suspended,
            'branch_id' => $branches['centro']->id,
            'invited_at' => now()->subDays(3),
            'joined_at' => null,
        ]);

        // Veinte personas evaluables sin cuenta. Con las seis anteriores son
        // veintiseis filas: suficiente para que aparezca la paginacion, que
        // corta en veinte.
        $nombres = [
            ['Lucia', 'Ramirez'], ['Miguel', 'Torres'], ['Elena', 'Vargas'],
            ['Jorge', 'Mendoza'], ['Patricia', 'Nunez'], ['Raul', 'Castillo'],
            ['Gabriela', 'Ortiz'], ['Fernando', 'Rios'], ['Adriana', 'Peralta'],
            ['Hector', 'Salas'], ['Monica', 'Aguilar'], ['Ricardo', 'Cordova'],
            ['Teresa', 'Blanco'], ['Alberto', 'Zamora'], ['Silvia', 'Farias'],
            ['Oscar', 'Duarte'], ['Rosa', 'Ibarra'], ['Daniel', 'Quiroz'],
            ['Norma', 'Escobar'], ['Julio', 'Barrera'],
        ];

        foreach ($nombres as $indice => [$nombre, $apellido]) {
            $area = $areas[$indice % max($areas->count(), 1)] ?? null;

            StaffMember::query()->create([
                'organization_id' => $organization->id,
                'membership_id' => null,
                'first_name' => $nombre,
                'last_name' => $apellido,
                'employee_code' => sprintf('EMP-%03d', $indice + 1),
                'branch_id' => $area?->branch_id,
                'area_id' => $area?->id,
            ]);
        }
    }

    private function user(string $name, string $email, string $password, bool $platformAdmin = false): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_platform_admin' => $platformAdmin,
        ]);

        /*
         * email_verified_at se asigna aparte, no en el create().
         *
         * No esta en $fillable de User, y con Model::shouldBeStrict() eso
         * lanza una excepcion en lugar de descartarse en silencio. La
         * tentacion es anadirlo a $fillable para que el seeder funcione, y
         * seria el arreglo equivocado: $fillable dice que puede llegar de un
         * formulario, y "tu correo ya esta verificado" no es algo que deba
         * poder enviar nadie desde fuera.
         *
         * El modelo esta bien. El que se saltaba la regla era el seeder.
         */
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function summary(string $password, User $admin): void
    {
        $this->command?->newLine();
        $this->command?->info('Cuentas de desarrollo creadas. Contrasena: la de SEED_PASSWORD.');
        $this->command?->newLine();

        $this->command?->table(
            ['Rol', 'Correo'],
            [
                ['Administrador de organizacion', $admin->email],
                ['Segundo administrador', 'admin2'.self::DOMAIN],
                ['Colaborador (quiosco)', 'colaborador'.self::DOMAIN],
                ['Administrador de plataforma', 'plataforma'.self::DOMAIN],
                ['Membresia suspendida', 'suspendida'.self::DOMAIN],
                ['Invitacion sin usar', 'invitada'.self::DOMAIN],
            ]
        );

        $this->command?->newLine();
        $this->command?->line('  Las dos ultimas NO pueden iniciar sesion: estan ahi para ver el rechazo.');
        $this->command?->newLine();
    }
}
