<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Application\Surveys\UpdateVersionSettings;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\Membership;
use App\Domain\Surveys\Enums\IdentityMode;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Domain\Surveys\VersionSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * RF-AO-PUB-001 y RF-AO-PUB-007.
 */
final class VersionSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_version_sin_configuracion_usa_la_de_por_defecto(): void
    {
        // El cast nunca devuelve null: asi ninguna pantalla tiene que
        // preguntar "y si no hay nada", que es donde nacen los null que se
        // propagan.
        $version = $this->draft();

        $this->assertInstanceOf(VersionSettings::class, $version->settings);
        $this->assertSame(IdentityMode::Anonymous, $version->settings->identityMode);
    }

    public function test_el_modo_por_defecto_es_anonimo(): void
    {
        /*
         * No es una preferencia estetica. Si alguien crea una encuesta y
         * publica sin abrir esta pantalla, lo que ocurre es que NO se recogen
         * datos personales. El defecto contrario convertiria un descuido en
         * una captura que nadie autorizo.
         */
        $this->assertSame(IdentityMode::Anonymous, VersionSettings::default()->identityMode);
        $this->assertFalse(VersionSettings::default()->identityMode->capturesIdentity());
    }

    public function test_se_guarda_la_configuracion_en_el_borrador(): void
    {
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->create(['organization_id' => $admin->organization_id]);

        $this->put(route('admin.surveys.settings.update', $survey), [
            'identity_mode' => 'confidential',
            'comment_mode' => 'required',
            'inactivity_seconds' => 120,
            'allow_back' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settings = $survey->fresh()->draft->settings;

        $this->assertSame(IdentityMode::Confidential, $settings->identityMode);
        $this->assertSame(120, $settings->inactivitySeconds);
        $this->assertTrue($settings->allowBack);
        $this->assertFalse($settings->helpEnabled);
    }

    public function test_no_se_modifica_una_version_publicada(): void
    {
        /*
         * RF-AO-PUB-007. La comprobacion vive en el caso de uso y no solo en
         * la Policy: este mismo caso de uso se invocara desde el constructor y
         * desde la API, y una regla que solo vive en la puerta HTTP deja de
         * existir en cuanto aparece otra puerta.
         */
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        $publicada = SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $admin->organization_id,
        ]);

        $this->expectException(RuntimeException::class);

        app(UpdateVersionSettings::class)->execute(
            $publicada,
            new VersionSettings(identityMode: IdentityMode::Identified),
        );
    }

    public function test_editar_la_configuracion_de_una_encuesta_publicada_abre_un_borrador(): void
    {
        // Quien entra a cambiar el modo de identidad no tiene por que saber
        // que antes hay que crear una version.
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        $publicada = SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $admin->organization_id,
            'settings' => ['identity_mode' => 'anonymous'],
        ]);

        $this->put(route('admin.surveys.settings.update', $survey), [
            'identity_mode' => 'identified',
            'comment_mode' => 'optional',
            'inactivity_seconds' => 60,
        ])->assertRedirect()->assertSessionHasNoErrors();

        // La publicada, intacta.
        $this->assertSame(
            IdentityMode::Anonymous,
            $publicada->fresh()->settings->identityMode,
        );

        // Y el cambio, en un borrador nuevo.
        $this->assertSame(
            IdentityMode::Identified,
            $survey->fresh()->draft->settings->identityMode,
        );
    }

    public function test_la_inactividad_tiene_limites(): void
    {
        // Por debajo del minimo, quien lee una pregunta antes de contestarla
        // veria la pantalla reiniciarse en la cara.
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->create(['organization_id' => $admin->organization_id]);

        /*
         * Sin flushSession entre iteraciones.
         *
         * flushSession borra la sesion ENTERA, incluida la organizacion
         * activa, asi que la segunda vuelta llegaba sin sesion y el error que
         * salia era "tu sesion ya no tiene una organizacion valida" en lugar
         * del de validacion. Un fallo de la prueba disfrazado de fallo del
         * codigo.
         */
        foreach ([VersionSettings::MIN_INACTIVITY_SECONDS - 1, VersionSettings::MAX_INACTIVITY_SECONDS + 1] as $valor) {
            $this->put(route('admin.surveys.settings.update', $survey), [
                'identity_mode' => 'anonymous',
                'comment_mode' => 'optional',
                'inactivity_seconds' => $valor,
            ])->assertSessionHasErrors('inactivity_seconds');
        }

        // Y el valor guardado sigue siendo el de por defecto: ninguno de los
        // dos intentos llego a escribir.
        $this->assertSame(
            VersionSettings::DEFAULT_INACTIVITY_SECONDS,
            $survey->fresh()->draft->settings->inactivitySeconds,
        );
    }

    public function test_un_modo_de_identidad_inventado_se_rechaza(): void
    {
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->create(['organization_id' => $admin->organization_id]);

        $this->put(route('admin.surveys.settings.update', $survey), [
            'identity_mode' => 'inventado',
            'comment_mode' => 'optional',
            'inactivity_seconds' => 60,
        ])->assertSessionHasErrors('identity_mode');
    }

    public function test_una_clave_desconocida_no_se_guarda(): void
    {
        /*
         * El motivo entero de VersionSettings: un jsonb sin forma declarada
         * es donde se acumula lo que nadie valida. Si esta prueba fallara,
         * dentro de tres fases habria versiones con claves distintas segun
         * cuando se crearon.
         */
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->create(['organization_id' => $admin->organization_id]);

        $this->put(route('admin.surveys.settings.update', $survey), [
            'identity_mode' => 'anonymous',
            'comment_mode' => 'optional',
            'inactivity_seconds' => 60,
            'clave_inventada' => 'valor',
        ]);

        $guardado = $survey->fresh()->draft->getRawOriginal('settings');

        $this->assertStringNotContainsString('clave_inventada', (string) $guardado);
        $this->assertStringContainsString('identity_mode', (string) $guardado);
    }

    public function test_el_cambio_de_modo_queda_auditado_con_el_antes_y_el_despues(): void
    {
        // Es el ajuste con consecuencias sobre datos personales: saber cuando
        // cambio importa mas que saber que se edito la configuracion.
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();
        SurveyVersion::factory()->for($survey)->create(['organization_id' => $admin->organization_id]);

        $this->put(route('admin.surveys.settings.update', $survey), [
            'identity_mode' => 'identified',
            'comment_mode' => 'optional',
            'inactivity_seconds' => 60,
        ]);

        $entrada = AuditLog::query()
            ->where('action', 'survey_version.settings_updated')
            ->firstOrFail();

        $this->assertSame('anonymous', $entrada->context['identity_mode_before']);
        $this->assertSame('identified', $entrada->context['identity_mode_after']);
    }

    public function test_no_se_configura_una_encuesta_ajena(): void
    {
        $this->admin();
        $ajena = Survey::factory()->create();

        $this->get(route('admin.surveys.settings', $ajena))->assertForbidden();
    }

    private function draft(): SurveyVersion
    {
        $admin = $this->admin();
        $survey = Survey::factory()->for($admin->organization)->create();

        return SurveyVersion::factory()->for($survey)->create([
            'organization_id' => $admin->organization_id,
        ]);
    }

    private function admin(): Membership
    {
        $membership = Membership::factory()->create();

        $this->post('/login', [
            'email' => $membership->user->email,
            'password' => 'password',
        ]);

        return $membership;
    }
}
