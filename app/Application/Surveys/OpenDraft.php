<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Surveys\Enums\SurveyVersionStatus;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Support\Facades\DB;

/**
 * Abre un borrador nuevo a partir de la ultima version. RF-AO-SUR-007.
 *
 * Es la regla que sostiene todo el versionado: los cambios posteriores a una
 * publicacion NO tocan la version publicada. Si la tocaran, las respuestas ya
 * guardadas cambiarian de significado sin que nadie lo pidiera: alguien
 * contesto a una pregunta y el informe mostraria otra.
 */
final class OpenDraft
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(Survey $survey): SurveyVersion
    {
        return DB::transaction(function () use ($survey): SurveyVersion {
            // Si ya hay borrador, se devuelve ese. Crear otro chocaria con el
            // indice parcial, y ademas el usuario espera continuar donde lo
            // dejo, no empezar de cero.
            $existente = $survey->draft()->first();

            if ($existente !== null) {
                return $existente;
            }

            /*
             * El numero sale de un bloqueo sobre las versiones de esta
             * encuesta. Sin el, dos peticiones simultaneas leerian el mismo
             * maximo y la segunda chocaria con el unique. RNF-AO-PUB-001 pide
             * que el numero se asigne dentro de una transaccion.
             *
             * Se traen las filas y se calcula en PHP: PostgreSQL no permite
             * FOR UPDATE con funciones de agregacion (T-026).
             */
            $versiones = SurveyVersion::query()
                ->where('survey_id', $survey->id)
                ->lockForUpdate()
                ->get();

            $ultima = $versiones->sortByDesc('version_number')->first();

            $draft = SurveyVersion::query()->create([
                'survey_id' => $survey->id,
                'organization_id' => $survey->organization_id,
                'version_number' => (int) ($versiones->max('version_number') ?? 0) + 1,

                /*
                 * status va explicito aunque la base lo ponga por defecto.
                 *
                 * create() devuelve un modelo con SOLO los atributos que se
                 * le pasaron. Sin esta linea, el objeto que sale de aqui no
                 * tiene status, y quien pregunte isDraft() compara contra null
                 * y concluye que la version esta publicada. La base estaba
                 * bien; el objeto en memoria, incompleto. Es T-027.
                 */
                'status' => SurveyVersionStatus::Draft,

                // El borrador nuevo parte de la configuracion de la anterior:
                // empezar en blanco obligaria a reescribir todos los ajustes
                // para cambiar una coma.
                'settings' => $ultima?->settings,
            ]);

            $this->audit->record('survey_version.draft_opened', $draft, [
                'version_number' => $draft->version_number,
            ]);

            return $draft;
        });
    }
}
