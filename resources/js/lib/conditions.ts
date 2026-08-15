export interface Condition {
    depends_on_ulid: string
    option_ulid: string
}

interface QuestionLike {
    ulid: string | null
    text: string
    condition: Condition | null
}

/**
 * Las mismas reglas que ConditionRules en PHP. RF-AO-BLD-007.
 *
 * Duplicar una regla es lo que hace que un dia las dos digan cosas distintas,
 * asi que esto merece explicacion: aqui NO se decide nada, se anticipa. El
 * servidor rechaza; esto solo evita ofrecer un boton que ya se sabe que va a
 * fallar.
 *
 * Si divergieran, el sintoma seria un movimiento que la pantalla permite y el
 * servidor rechaza —molesto, pero visible y sin dano—. Lo contrario, que la
 * pantalla impida algo que el servidor acepta, tampoco rompe nada.
 *
 * La alternativa era preguntar al servidor en cada pulsacion de "Subir". Con
 * RNF-AO-BLD-003 pidiendo menos de 200 ms, no es una opcion.
 */

/** Si mover la pregunta del indice a la direccion dejaria alguna condicion mirando hacia delante. */
export function movementBreaksCondition(
    questions: QuestionLike[],
    index: number,
    direction: -1 | 1,
): boolean {
    const destino = index + direction

    if (destino < 0 || destino >= questions.length) {
        return false
    }

    const propuesta = [...questions]
    const [movida] = propuesta.splice(index, 1)

    if (movida) {
        propuesta.splice(destino, 0, movida)
    }

    return hasForwardCondition(propuesta)
}

/** Las preguntas que dependen de esta, por su posicion visible. */
export function dependentsOf(questions: QuestionLike[], ulid: string | null): number[] {
    if (ulid === null) {
        return []
    }

    return questions
        .map((question, index) => (question.condition?.depends_on_ulid === ulid ? index + 1 : 0))
        .filter((position) => position > 0)
}

/** Las preguntas ANTERIORES a esta, que son las unicas de las que puede depender. */
export function availableSources<T extends QuestionLike>(questions: T[], index: number): T[] {
    return questions.slice(0, index).filter((question) => question.ulid !== null)
}

function hasForwardCondition(questions: QuestionLike[]): boolean {
    const positions = new Map<string, number>()

    questions.forEach((question, index) => {
        if (question.ulid !== null) {
            positions.set(question.ulid, index + 1)
        }
    })

    return questions.some((question, index) => {
        if (question.condition === null) {
            return false
        }

        const origen = positions.get(question.condition.depends_on_ulid)

        // Sin origen, la condicion se quedo sin referencia: cuenta como rota.
        return origen === undefined || origen >= index + 1
    })
}
