<?php

declare(strict_types=1);

use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BuilderController;
use App\Http\Controllers\Admin\DeploymentController;
use App\Http\Controllers\Admin\DeploymentQrController;
use App\Http\Controllers\Admin\PersonController;
use App\Http\Controllers\Admin\QuestionImportController;
use App\Http\Controllers\Admin\StaffMemberController;
use App\Http\Controllers\Admin\SurveyController;
use App\Http\Controllers\Admin\VersionSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OrganizationChoiceController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\SecondFactorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\PublicResponseController;
use App\Http\Controllers\PublicSurveyController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/*
 * Autenticacion. RF-AUT-001 a 006.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    /*
     * Recuperacion de contrasena. RF-AUT-008 a 013.
     *
     * Los nombres password.request, password.email, password.reset y
     * password.store son los que Laravel espera: la notificacion que envia
     * el broker construye la liga con route('password.reset'). Renombrarlos
     * romperia el correo sin que ninguna prueba de rutas lo notara.
     */
    Route::get('/recuperar-contrasena', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('/restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/restablecer-contrasena', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

/*
 * Verificacion del segundo factor. RF-AUT-007, 014 y 015.
 *
 * NO va detras de 'auth': en este punto el usuario todavia no tiene sesion.
 * La puerta es 'pending', que exige un identificador pendiente en la sesion
 * parcial.
 */
Route::middleware('pending')->group(function (): void {
    Route::get('/verificacion', [SecondFactorController::class, 'create'])
        ->name('auth.second-factor.challenge');
    Route::post('/verificacion', [SecondFactorController::class, 'store']);
    Route::post('/verificacion/reenviar', [SecondFactorController::class, 'resend'])
        ->name('auth.second-factor.resend');
    Route::post('/verificacion/cancelar', [SecondFactorController::class, 'destroy'])
        ->name('auth.second-factor.cancel');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    /*
     * Seguridad de la cuenta. Acordada en P-011: sin ella nadie puede activar
     * el segundo factor y la pantalla de verificacion seria inalcanzable.
     *
     * Es de la cuenta, no de la organizacion, asi que no lleva el middleware
     * 'organization': un administrador de plataforma tambien necesita entrar.
     */
    Route::get('/cuenta/seguridad', [SecurityController::class, 'show'])
        ->name('account.security');
    Route::post('/cuenta/seguridad/mfa', [SecurityController::class, 'enable'])
        ->name('account.security.enable');
    Route::delete('/cuenta/seguridad/mfa', [SecurityController::class, 'disable'])
        ->name('account.security.disable');
    Route::post('/cuenta/seguridad/codigos', [SecurityController::class, 'regenerate'])
        ->name('account.security.codes');

    Route::get('/organizaciones', [OrganizationChoiceController::class, 'create'])
        ->name('auth.organizations.choose');
    Route::post('/organizaciones', [OrganizationChoiceController::class, 'store']);
});

/*
 * Destinos de RF-AUT-003.
 *
 * Son marcadores deliberados: cada uno se sustituye por su modulo en la fase
 * que le toca. Existen ahora porque la redireccion por rol no se puede
 * probar contra rutas inexistentes, y una prueba que espera un 404 no prueba
 * lo que dice probar.
 */
Route::middleware(['auth', 'platform'])->group(function (): void {
    Route::get('/platform', fn () => app(PlaceholderController::class)('Panel de plataforma'))
        ->name('platform.dashboard');
});

Route::middleware(['auth', 'organization'])->group(function (): void {
    Route::middleware('role:admin')->group(function (): void {
        /*
         * El panel usa el marco de administracion, no la vista marcador.
         *
         * Con el marcador, quien entraba aterrizaba en una pagina sin barra
         * lateral y sin forma de llegar a sucursales ni a personas: las
         * secciones existian y eran inalcanzables salvo escribiendo la
         * direccion.
         */
        Route::get('/admin', DashboardController::class)->name('admin.dashboard');

        /*
         * Sucursales. RF-AO-BRA-001, 002 y 004.
         *
         * Sin resource(): no hay destroy. RF-GEN-010 prohibe el borrado
         * fisico de entidades con historial, asi que archive/activate
         * sustituyen a delete y decirlo en la ruta evita que alguien anada
         * un destroy por costumbre.
         */
        Route::get('/admin/sucursales', [BranchController::class, 'index'])
            ->name('admin.branches.index');
        Route::get('/admin/sucursales/nueva', [BranchController::class, 'create'])
            ->name('admin.branches.create');
        Route::post('/admin/sucursales', [BranchController::class, 'store'])
            ->name('admin.branches.store');
        Route::get('/admin/sucursales/{branch}/editar', [BranchController::class, 'edit'])
            ->name('admin.branches.edit');
        Route::put('/admin/sucursales/{branch}', [BranchController::class, 'update'])
            ->name('admin.branches.update');
        Route::post('/admin/sucursales/{branch}/archivar', [BranchController::class, 'archive'])
            ->name('admin.branches.archive');
        Route::post('/admin/sucursales/{branch}/activar', [BranchController::class, 'activate'])
            ->name('admin.branches.activate');

        /*
         * Usuarios y colaboradores. RF-AO-COL-001 a 006.
         *
         * El parametro es el id de la membresia y no un ULID: memberships no
         * tiene columna ulid en el modelo aprobado en TASK-004. La Policy
         * comprueba la organizacion en cada accion, asi que un id ajeno da
         * 403 y no toca nada. Queda anotado como P-019 por consistencia con
         * el resto de las rutas.
         */
        Route::get('/admin/personas', [PersonController::class, 'index'])
            ->name('admin.people.index');
        Route::get('/admin/personas/invitar', [PersonController::class, 'create'])
            ->name('admin.people.create');
        Route::post('/admin/personas', [PersonController::class, 'store'])
            ->name('admin.people.store');
        Route::post('/admin/personas/{membership}/suspender', [PersonController::class, 'suspend'])
            ->name('admin.people.suspend');
        Route::post('/admin/personas/{membership}/activar', [PersonController::class, 'activate'])
            ->name('admin.people.activate');
        Route::put('/admin/personas/{membership}/asignacion', [PersonController::class, 'assign'])
            ->name('admin.people.assign');

        /*
         * Personas evaluables sin cuenta. RF-AO-COL-002 y P-016.
         *
         * El parametro se llama {staffMember} para que el enlace implicito
         * deduzca la clase por convencion y resuelva por ulid, que es el
         * route key del modelo.
         *
         * Se llamo {staffMember} un momento, con un Route::model para atarlo a
         * mano. Era peor por dos motivos: el nombre del parametro NO aparece
         * en la URL —solo su valor— asi que no aportaba legibilidad, y
         * ataba el enlace a un mecanismo que no podia comprobar.
         */
        Route::get('/admin/personas/registrar', [StaffMemberController::class, 'create'])
            ->name('admin.people.person.create');
        Route::post('/admin/personas/registrar', [StaffMemberController::class, 'store'])
            ->name('admin.people.person.store');
        Route::get('/admin/personas/{staffMember}/editar', [StaffMemberController::class, 'edit'])
            ->name('admin.people.person.edit');
        Route::put('/admin/personas/{staffMember}', [StaffMemberController::class, 'update'])
            ->name('admin.people.person.update');
        Route::post('/admin/personas/{staffMember}/archivar', [StaffMemberController::class, 'archive'])
            ->name('admin.people.person.archive');
        Route::post('/admin/personas/{staffMember}/activar', [StaffMemberController::class, 'activate'])
            ->name('admin.people.person.activate');
        Route::get('/admin/personas/{staffMember}/cuenta', [StaffMemberController::class, 'accountForm'])
            ->name('admin.people.person.account');
        Route::post('/admin/personas/{staffMember}/cuenta', [StaffMemberController::class, 'grantAccount'])
            ->name('admin.people.person.account.store');

        /*
         * Encuestas y versiones. RF-AO-SUR-001 a 008.
         *
         * Sin destroy: RF-AO-SUR-004 prohibe el borrado fisico cuando hay
         * versiones publicadas o respuestas, y RF-GEN-010 lo prohibe en
         * general para entidades con historial. Archivar ocupa su lugar.
         */
        Route::get('/admin/encuestas', [SurveyController::class, 'index'])
            ->name('admin.surveys.index');
        Route::get('/admin/encuestas/nueva', [SurveyController::class, 'create'])
            ->name('admin.surveys.create');
        Route::post('/admin/encuestas', [SurveyController::class, 'store'])
            ->name('admin.surveys.store');
        /*
         * Constructor. RF-AO-BLD-001 a 010.
         *
         * Va ANTES de /admin/encuestas/{survey} porque Laravel resuelve por
         * orden de declaracion: con la ruta generica delante, "constructor"
         * se interpretaria como el ulid de una encuesta.
         */
        Route::get('/admin/encuestas/{survey}/constructor', [BuilderController::class, 'edit'])
            ->name('admin.surveys.builder');
        Route::put('/admin/encuestas/{survey}/constructor', [BuilderController::class, 'update'])
            ->name('admin.surveys.builder.update');

        Route::get('/admin/encuestas/{survey}', [SurveyController::class, 'edit'])
            ->name('admin.surveys.edit');
        Route::put('/admin/encuestas/{survey}', [SurveyController::class, 'update'])
            ->name('admin.surveys.update');
        Route::post('/admin/encuestas/{survey}/borrador', [SurveyController::class, 'draft'])
            ->name('admin.surveys.draft');

        Route::post('/admin/encuestas/{survey}/publicar', [SurveyController::class, 'publish'])
            ->name('admin.surveys.publish');

        Route::get('/admin/aplicaciones', [DeploymentController::class, 'index'])
            ->name('admin.deployments.index');

        /*
         * Crear parte SIEMPRE de una encuesta: la version publicada es
         * contexto de la ruta, no algo que se elija en un desplegable.
         */
        Route::get('/admin/encuestas/{survey}/aplicaciones/nueva', [DeploymentController::class, 'create'])
            ->name('admin.deployments.create');
        Route::post('/admin/encuestas/{survey}/aplicaciones', [DeploymentController::class, 'store'])
            ->name('admin.deployments.store');

        Route::post('/admin/aplicaciones/{deployment}/activar', [DeploymentController::class, 'activate'])
            ->name('admin.deployments.activate');
        Route::post('/admin/aplicaciones/{deployment}/suspender', [DeploymentController::class, 'suspend'])
            ->name('admin.deployments.suspend');
        Route::post('/admin/aplicaciones/{deployment}/cerrar', [DeploymentController::class, 'close'])
            ->name('admin.deployments.close');

        Route::get('/admin/aplicaciones/{deployment}/qr', [DeploymentQrController::class, 'show'])
            ->name('admin.deployments.qr');
        Route::get('/admin/aplicaciones/{deployment}/qr.svg', [DeploymentQrController::class, 'svg'])
            ->name('admin.deployments.qr.svg');
        Route::post('/admin/aplicaciones/{deployment}/qr/regenerar', [DeploymentQrController::class, 'regenerate'])
            ->name('admin.deployments.qr.regenerate');

        /*
         * Importar preguntas desde texto. TASK-025.
         *
         * "comprobar" analiza sin guardar: ver lo que va a entrar evita
         * descubrir despues que el tipo era otro y deshacerlo a mano.
         */
        Route::get('/admin/encuestas/{survey}/importar', [QuestionImportController::class, 'create'])
            ->name('admin.surveys.import');
        Route::post('/admin/encuestas/{survey}/importar/comprobar', [QuestionImportController::class, 'preview'])
            ->name('admin.surveys.import.preview');
        Route::post('/admin/encuestas/{survey}/importar', [QuestionImportController::class, 'store'])
            ->name('admin.surveys.import.store');
        Route::post('/admin/encuestas/{survey}/archivar', [SurveyController::class, 'archive'])
            ->name('admin.surveys.archive');
        Route::post('/admin/encuestas/{survey}/activar', [SurveyController::class, 'activate'])
            ->name('admin.surveys.activate');

        /*
         * Configuracion de la version. RF-AO-PUB-001.
         *
         * Cuelga de la encuesta y no de la version: siempre se edita el
         * borrador, y pedir su identificador en la URL obligaria a quien
         * llega a saber cual es.
         */
        Route::get('/admin/encuestas/{survey}/configuracion', [VersionSettingsController::class, 'edit'])
            ->name('admin.surveys.settings');
        Route::put('/admin/encuestas/{survey}/configuracion', [VersionSettingsController::class, 'update'])
            ->name('admin.surveys.settings.update');

        /*
         * Areas, anidadas bajo su sucursal. RF-AO-BRA-001.
         *
         * scopeBindings(): Laravel comprueba que el area pertenezca a la
         * sucursal de la URL antes de entrar al controlador. Es la misma
         * regla que ensureBelongsTo(), aplicada una capa antes.
         */
        Route::prefix('/admin/sucursales/{branch}/areas')
            ->scopeBindings()
            ->group(function (): void {
                Route::get('/', [AreaController::class, 'index'])
                    ->name('admin.areas.index');
                Route::get('/nueva', [AreaController::class, 'create'])
                    ->name('admin.areas.create');
                Route::post('/', [AreaController::class, 'store'])
                    ->name('admin.areas.store');
                Route::get('/{area}/editar', [AreaController::class, 'edit'])
                    ->name('admin.areas.edit');
                Route::put('/{area}', [AreaController::class, 'update'])
                    ->name('admin.areas.update');
                Route::post('/{area}/archivar', [AreaController::class, 'archive'])
                    ->name('admin.areas.archive');
                Route::post('/{area}/activar', [AreaController::class, 'activate'])
                    ->name('admin.areas.activate');
            });
    });

    Route::middleware('role:collaborator')->group(function (): void {
        Route::get('/kiosk/start', fn () => app(PlaceholderController::class)('Preparacion de quiosco'))
            ->name('kiosk.start');
    });
});

/*
 * La puerta publica de una encuesta.
 *
 * Sin sesion, sin organizacion activa y sin rol: quien escanea un cartel no
 * ha iniciado sesion en nada.
 */
Route::get('/e/{token}', PublicSurveyController::class)->name('public.survey');

/*
 * Recibir una encuesta contestada. Tambien publica: lo que autoriza es el
 * token del enlace, no una sesion.
 */
Route::post('/e/{token}', PublicResponseController::class)->name('public.survey.submit');
