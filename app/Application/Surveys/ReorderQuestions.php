<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Domain\Audit\RecordAuditLog;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reordenar preguntas. RF-AO-BLD-001 y RNF-AO-BLD-002.
 */
final class ReorderQuestions
{
    public function __construct(
        private readonly BuilderGuard $guard,
        private readonly RecordAuditLog $audit,
    ) {}

    /**
     * @param  list<string>  $ulids  Todas las preguntas de la version, en el
     *                               orden deseado.
     */
    public function execute(SurveyVersion $version, array $ulids): void
    {
        $this->guard->ensureEditable($version);

        DB::transaction(function () use ($version, $ulids): void {
            $preguntas = SurveyQuestion::query()
                ->where('survey_version_id', $version->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('ulid');

            /*
             * La lista tiene que traer TODAS las preguntas, ni una mas ni una
             * menos.
             *
             * Aceptar una lista parcial obligaria a decidir que hacer con las
             * que faltan —dejarlas al final, intercalarlas— y cualquiera de
             * esas decisiones seria una invencion. Mejor rechazar la peticion
             * que reordenar de una forma que nadie pidio.
             */
            if (count($ulids) !== $preguntas->count() || array_diff($ulids, $preguntas->keys()->all()) !== []) {
                throw new InvalidArgumentException(
                    'La lista de orden debe contener exactamente las preguntas de esta version.'
                );
            }

            /*
             * Se reasignan todas las posiciones de una vez.
             *
             * La restriccion de unicidad es DEFERRABLE INITIALLY DEFERRED, asi
             * que el estado intermedio —donde dos preguntas comparten numero—
             * es valido hasta que la transaccion cierra. Sin eso habria que
             * pasar por posiciones negativas o hacer dos vueltas, que es el
             * truco clasico que nadie entiende seis meses despues.
             */
            foreach ($ulids as $indice => $ulid) {
                $preguntas[$ulid]->forceFill(['position' => $indice + 1])->save();
            }

            $this->audit->record('survey_version.questions_reordered', $version, [
                'count' => count($ulids),
            ]);
        });
    }
}
