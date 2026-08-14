<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Domain\Surveys\VersionSettings;
use RuntimeException;

/**
 * RF-AO-PUB-001. Solo sobre el borrador.
 */
final class UpdateVersionSettings
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(SurveyVersion $version, VersionSettings $settings): SurveyVersion
    {
        /*
         * RF-AO-PUB-007: una version publicada es inmutable.
         *
         * La comprobacion esta aqui y no solo en la Policy porque este caso de
         * uso tambien se invocara desde el constructor y desde la API. Una
         * regla que solo vive en la puerta HTTP deja de existir en cuanto
         * aparece otra puerta.
         */
        if (! $version->isEditable()) {
            throw new RuntimeException(
                'Una version publicada no se modifica. Abre un borrador nuevo.'
            );
        }

        $anterior = $version->settings;

        $version->forceFill(['settings' => $settings])->save();

        // El modo de identidad se registra en el antes y el despues: es el
        // ajuste con consecuencias sobre datos personales, y saber cuando
        // cambio importa mas que saber que se edito la configuracion.
        $this->audit->record('survey_version.settings_updated', $version, [
            'identity_mode_before' => $anterior->identityMode->value,
            'identity_mode_after' => $settings->identityMode->value,
        ]);

        return $version;
    }
}
