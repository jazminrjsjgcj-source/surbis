<?php

declare(strict_types=1);

namespace App\Domain\Surveys;

use Illuminate\Support\Collection;

/**
 * Que movimientos y borrados rompen una condicion. RF-AO-BLD-007.
 *
 * Trabaja sobre la lista de preguntas TAL COMO ESTA en el cliente, no sobre
 * modelos: el constructor guarda el borrador entero (D-025), asi que la
 * comprobacion tiene que poder hacerse antes de que nada exista en la base.
 *
 * Y vive aqui, en el dominio, porque las dos capas la necesitan: el servidor
 * para rechazar, y el cliente para deshabilitar los botones que romperian
 * algo. Duplicarla garantizaria que un dia dijeran cosas distintas.
 */
final class ConditionRules
{
    /**
     * Las condiciones que quedarian mirando hacia delante con este orden.
     *
     * Una condicion apunta hacia delante cuando su pregunta origen queda
     * DESPUES de la pregunta condicionada. Entonces quien contesta llegaria a
     * la pregunta sin haber respondido de que depende.
     *
     * @param  list<array<string, mixed>>  $questions  En el orden propuesto.
     * @return Collection<int, array{position: int, depends_on_position: int, text: string}>
     */
    public function forwardConditions(array $questions): Collection
    {
        $positions = [];

        foreach ($questions as $index => $question) {
            if (isset($question['ulid'])) {
                $positions[$question['ulid']] = $index + 1;
            }
        }

        $broken = collect();

        foreach ($questions as $index => $question) {
            $condition = $question['condition'] ?? null;

            if ($condition === null || ! isset($condition['depends_on_ulid'])) {
                continue;
            }

            $own = $index + 1;
            $origin = $positions[$condition['depends_on_ulid']] ?? null;

            /*
             * Si la pregunta origen ya no esta en la lista, la condicion se
             * quedo sin referencia. Se cuenta como rota: es el caso de haber
             * borrado la pregunta de la que algo dependia.
             */
            if ($origin === null || $origin >= $own) {
                $broken->push([
                    'position' => $own,
                    'depends_on_position' => $origin ?? 0,
                    'text' => (string) ($question['text'] ?? ''),
                ]);
            }
        }

        return $broken;
    }

    public function allows(array $questions): bool
    {
        return $this->forwardConditions($questions)->isEmpty();
    }

    /**
     * Que preguntas dependen de la que ocupa esta posicion.
     *
     * Sirve para decir POR QUE no se puede borrar o mover algo. "No se puede"
     * sin decir que lo impide obliga a probar una por una.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return list<int> Posiciones de las preguntas dependientes.
     */
    public function dependentsOf(array $questions, string $ulid): array
    {
        $dependents = [];

        foreach ($questions as $index => $question) {
            $condition = $question['condition'] ?? null;

            if (($condition['depends_on_ulid'] ?? null) === $ulid) {
                $dependents[] = $index + 1;
            }
        }

        return $dependents;
    }
}
