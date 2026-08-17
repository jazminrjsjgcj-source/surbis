<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Surveys\Enums\SurveyVersionStatus;
use App\Domain\Surveys\Models\Survey;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionCondition;
use App\Domain\Surveys\Models\SurveyQuestionOption;
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

            /*
             * Las preguntas se COPIAN de la version anterior.
             *
             * Un borrador vacio obligaria a reescribir la encuesta entera
             * para cambiar una coma, y nadie va a teclear veinte preguntas
             * otra vez: se quedaria sin corregir.
             *
             * Se copian —no se comparten— porque las respuestas guardan el
             * texto de la pregunta que se hizo. Si las versiones compartieran
             * preguntas, cambiar una en la v2 cambiaria lo que dice la
             * respuesta de quien contesto la v1, y la fotografia historica
             * dejaria de ser fiel.
             */
            if ($ultima !== null) {
                $this->copyQuestions($ultima, $draft);
            }

            $this->audit->record('survey_version.draft_opened', $draft, [
                'version_number' => $draft->version_number,
                'copied_from' => $ultima?->version_number,
            ]);

            return $draft;
        });
    }

    /**
     * Duplica preguntas, opciones y condiciones en el borrador nuevo.
     *
     * Lo delicado son las CONDICIONES: apuntan a una pregunta y a una opcion
     * concretas. Copiarlas tal cual dejaria la version nueva señalando
     * preguntas de la version vieja, y editar una de ellas cambiaria el
     * comportamiento de la otra.
     *
     * Por eso se copia en dos pasadas: primero preguntas y opciones
     * guardando la correspondencia vieja→nueva, y despues las condiciones
     * traducidas contra esa correspondencia.
     */
    private function copyQuestions(SurveyVersion $origen, SurveyVersion $destino): void
    {
        $origen->load(['questions.options', 'questions.condition']);

        /** @var array<int, int> $preguntas */
        $preguntas = [];

        /** @var array<int, int> $opciones */
        $opciones = [];

        foreach ($origen->questions as $pregunta) {
            $copia = SurveyQuestion::query()->create([
                'survey_version_id' => $destino->id,
                'organization_id' => $pregunta->organization_id,
                'type' => $pregunta->type,
                'text' => $pregunta->text,
                'help' => $pregunta->help,
                'is_required' => $pregunta->is_required,
                'limits' => $pregunta->limits,
                'position' => $pregunta->position,
            ]);

            $preguntas[$pregunta->id] = $copia->id;

            foreach ($pregunta->options as $opcion) {
                $copiaOpcion = SurveyQuestionOption::query()->create([
                    'survey_question_id' => $copia->id,
                    'organization_id' => $opcion->organization_id,
                    'label' => $opcion->label,
                    'value' => $opcion->value,
                    'score' => $opcion->score,
                    'display' => $opcion->display,

                    /*
                     * La imagen SI se comparte: es un archivo de la
                     * biblioteca, no parte de la pregunta. Duplicarla dejaria
                     * dos copias del mismo archivo por cada version.
                     */
                    'media_id' => $opcion->media_id,

                    'appearance' => $opcion->appearance,
                    'position' => $opcion->position,
                ]);

                $opciones[$opcion->id] = $copiaOpcion->id;
            }
        }

        /*
         * Segunda pasada: ahora que existen todas las copias, las condiciones
         * pueden traducirse.
         *
         * En una sola pasada, una pregunta condicionada a otra que viene
         * DESPUES no encontraria su destino —y el constructor permite
         * reordenar, asi que ese caso ocurre—.
         */
        foreach ($origen->questions as $pregunta) {
            $condicion = $pregunta->condition;

            if ($condicion === null) {
                continue;
            }

            /*
             * Si falta alguna de las dos referencias no se copia la
             * condicion.
             *
             * Mejor una pregunta que siempre se muestra que una condicion
             * apuntando a la version anterior: lo primero se ve y se corrige;
             * lo segundo funciona mal en silencio.
             */
            if (! isset($preguntas[$condicion->depends_on_question_id], $opciones[$condicion->option_id])) {
                continue;
            }

            SurveyQuestionCondition::query()->create([
                'survey_question_id' => $preguntas[$pregunta->id],
                'organization_id' => $condicion->organization_id,
                'depends_on_question_id' => $preguntas[$condicion->depends_on_question_id],
                'option_id' => $opciones[$condicion->option_id],
            ]);
        }
    }
}
