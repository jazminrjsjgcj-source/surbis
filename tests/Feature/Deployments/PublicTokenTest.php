<?php

declare(strict_types=1);

namespace Tests\Feature\Deployments;

use App\Application\Deployments\CreateDeployment;
use App\Application\Deployments\PublicToken;
use App\Domain\Deployments\Enums\DeploymentChannel;
use App\Domain\Deployments\Enums\DeploymentScope;
use App\Domain\Identity\Models\Membership;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Device;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tokens publicos. RNF-AO-DEP-002.
 */
final class PublicTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_token_no_se_guarda_en_claro(): void
    {
        /*
         * Si la base se filtra, los enlaces ya publicados siguen sin poder
         * deducirse: para entrar hay que tener el token, y del hash no se
         * saca.
         */
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();
        $version = SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $membership->organization_id,
        ]);

        [$deployment, $token] = app(CreateDeployment::class)->execute(
            $membership->organization, $version, $membership->user,
            DeploymentChannel::PublicLink, DeploymentScope::Organization,
        );

        $this->assertNotNull($token);
        $this->assertNotSame($token, $deployment->public_token_hash);
        $this->assertDatabaseMissing('deployments', ['public_token_hash' => $token]);
    }

    public function test_dos_tokens_nunca_coinciden(): void
    {
        $tokens = app(PublicToken::class);
        $generados = collect(range(1, 200))->map(fn (): string => $tokens->generate());

        $this->assertSame(200, $generados->unique()->count());
    }

    public function test_el_quiosco_no_usa_token_publico(): void
    {
        /*
         * Su dispositivo se identifica con su propia clave de estacion, que
         * es otro mecanismo y tiene otras reglas de revocacion (TASK-005,
         * Fase 8). Darle ademas un token publico crearia una segunda puerta
         * que nadie vigila.
         */
        $membership = Membership::factory()->create();
        $survey = Survey::factory()->for($membership->organization)->create();
        $version = SurveyVersion::factory()->for($survey)->published(1)->create([
            'organization_id' => $membership->organization_id,
        ]);
        $branch = Branch::factory()->for($membership->organization)->create();
        $device = Device::factory()->for($branch)->create([
            'organization_id' => $membership->organization_id,
        ]);

        [$deployment, $token] = app(CreateDeployment::class)->execute(
            $membership->organization, $version, $membership->user,
            DeploymentChannel::Kiosk, DeploymentScope::Device,
            ['device' => $device],
        );

        $this->assertNull($token);
        $this->assertNull($deployment->public_token_hash);
    }

    public function test_la_comparacion_reconoce_el_token_correcto(): void
    {
        $tokens = app(PublicToken::class);
        $token = $tokens->generate();

        $this->assertTrue($tokens->matches($token, $tokens->hash($token)));
        $this->assertFalse($tokens->matches('otro-cualquiera', $tokens->hash($token)));
    }
}
