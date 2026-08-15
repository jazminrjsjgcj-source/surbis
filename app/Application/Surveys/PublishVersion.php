<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Application\Surveys\Exceptions\VersionNotEditable;
use App\Application\Surveys\Exceptions\VersionNotPublishable;
use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Surveys\Enums\SurveyStatus;
use App\Domain\Surveys\Enums\SurveyVersionStatus;
use App\Domain\Surveys\Models\SurveyVersion;
use App\Domain\Surveys\PublicationChecklist;
use Illuminate\Support\Facades\DB;

/**
 * Publicar una version. RF-AO-PUB-005, 006 y 007.
 *
 * El borrador SE CONVIERTE en la version publicada; no se copia. Decision del
 * area usuaria, 15 ago 2026.
 *
 * Es lo que hace que "inmutable" signifique algo: si el borrador siguiera
 * vivo despues de publicar, se podria seguir editando exactamente el
 * contenido que la gente ya esta contestando. Para cambiar algo hay que abrir
 * un borrador nuevo, que nace como copia y recibe el numero siguiente.
 */
final class PublishVersion
{
    public function __construct(
        private readonly PublicationChecklist $checklist,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @throws VersionNotEditable si ya esta publicada o retirada
     * @throws VersionNotPublishable si le falta algo
     */
    public function execute(SurveyVersion $version, User $publisher): SurveyVersion
    {
        if (! $version->isEditable()) {
            throw new VersionNotEditable;
        }

        $problems = $this->checklist->problems($version);

        if ($problems->isNotEmpty()) {
            throw new VersionNotPublishable($problems);
        }

        return DB::transaction(function () use ($version, $publisher): SurveyVersion {
            /*
             * Se vuelve a comprobar DENTRO de la transaccion y con bloqueo.
             *
             * Entre la comprobacion de arriba y este punto cabe otra peticion
             * entera: alguien podria haber publicado ya, o haber borrado la
             * ultima pregunta. RNF-AO-PUB-003 exige que publicar sea atomico,
             * y eso incluye que la condicion siga siendo cierta al escribir.
             */
            $actual = SurveyVersion::query()
                ->whereKey($version->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $actual->isEditable()) {
                throw new VersionNotEditable;
            }

            $survey = $actual->survey;

            /*
             * La version publicada anterior se ARCHIVA, no se borra.
             *
             * Las respuestas que la contestaron siguen apuntando a ella y su
             * contenido tiene que seguir ahi para poder leerlas. RF-GEN-010 y
             * RNF-DAT-009.
             *
             * Se usa Archived y no un estado "retirada" propio: los tres
             * estados del enum son draft, published y archived, y la columna
             * es un enum de PostgreSQL. Anadir un cuarto valor pide migracion
             * y no aporta nada aqui: para quien lea la encuesta, una version
             * sustituida y una archivada a mano significan lo mismo —ya no se
             * aplica, su historial se conserva—.
             */
            $survey->versions()
                ->where('status', SurveyVersionStatus::Published)
                ->update([
                    'status' => SurveyVersionStatus::Archived,
                    'updated_at' => now(),
                ]);

            $actual->forceFill([
                'status' => SurveyVersionStatus::Published,
                'published_at' => now(),
                'published_by' => $publisher->id,
            ])->save();

            $survey->forceFill(['status' => SurveyStatus::Published])->save();

            /*
             * RNF-AO-PUB-004: quien publico, cuando y que version.
             *
             * El actor va EXPLICITO. RecordAuditLog cae en
             * $request->user() cuando no se le pasa, y este caso de uso
             * recibe el publicador como argumento precisamente para poder
             * invocarse sin peticion web: desde la API, desde un comando o
             * desde una prueba.
             *
             * Sin esto, la entrada quedaria con user_id null y la auditoria
             * no diria quien publico, que es justo lo que el requisito pide.
             */
            $this->audit->record(
                'survey_version.published',
                $actual,
                [
                    'version_number' => $actual->version_number,
                    'questions' => $actual->questions()->count(),
                ],
                actor: $publisher,
            );

            return $actual;
        });
    }
}
