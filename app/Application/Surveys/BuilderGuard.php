<?php

declare(strict_types=1);

namespace App\Application\Surveys;

use App\Application\Surveys\Exceptions\VersionNotEditable;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyVersion;

/**
 * La comprobacion que abre todos los casos de uso del constructor.
 *
 * Existe como clase, y no repetida en cinco sitios, porque olvidarla en uno
 * solo permite editar una version publicada por esa via. Y ese fallo no se ve
 * al probar: la operacion funciona, guarda, y lo que cambia es el significado
 * de respuestas que ya estaban dadas.
 */
final class BuilderGuard
{
    /** @throws VersionNotEditable */
    public function ensureEditable(SurveyVersion $version): void
    {
        if (! $version->isEditable()) {
            throw new VersionNotEditable;
        }
    }

    /** @throws VersionNotEditable */
    public function ensureQuestionEditable(SurveyQuestion $question): void
    {
        $this->ensureEditable($question->version);
    }
}
