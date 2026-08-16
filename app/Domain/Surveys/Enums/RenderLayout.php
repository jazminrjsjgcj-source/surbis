<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Enums;

use App\Domain\Deployments\Enums\DeploymentChannel;

/**
 * Como se recorre una encuesta. Decision del area usuaria, 16 ago 2026.
 *
 * Lo que decide NO es el canal en si, sino el contexto de quien contesta:
 * de pie y con prisa, una pregunta cada vez; sentado y con calma, ver cuanto
 * queda.
 *
 * Lo decide el SERVIDOR y viaja como prop. Si React lo dedujera del canal, la
 * vista previa tendria que fingir un canal para verse bien, y ahi es donde
 * dos renderizadores acaban divergiendo aunque el codigo sea uno solo
 * (RNF-COL-012).
 */
enum RenderLayout: string
{
    /** Una pregunta por pantalla. Pulsar y avanzar, sin teclado. */
    case Stepped = 'stepped';

    /** Todas a la vez. Se ve el largo y se puede repasar antes de enviar. */
    case Full = 'full';

    public static function forChannel(DeploymentChannel $channel): self
    {
        return match ($channel) {
            // De pie, en una ventanilla o con el movil en la mano.
            DeploymentChannel::Kiosk, DeploymentChannel::Qr => self::Stepped,

            // Sentado, o incrustado en una pagina que ya tiene su propio
            // recorrido.
            DeploymentChannel::PublicLink, DeploymentChannel::Widget => self::Full,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
