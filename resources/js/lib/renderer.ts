export interface RenderOption {
    ulid: string
    label: string
    display: string
    image: { url: string; alt: string } | null
}

export interface RenderQuestion {
    ulid: string
    type: string
    text: string
    help: string | null
    isRequired: boolean
    limits: Record<string, number | string | null>
    options: RenderOption[]
    condition: { dependsOn: string; option: string } | null
}

export interface RenderableSurvey {
    name: string
    layout: 'stepped' | 'full'
    introduction: string | null
    thankYou: string | null
    allowBack: boolean
    commentMode: string
    inactivitySeconds: number
    questions: RenderQuestion[]
}

/** Lo que se ha contestado, por ULID de pregunta. */
export type Answers = Record<string, string | string[] | null>

/**
 * Si una pregunta debe mostrarse con las respuestas dadas hasta ahora.
 *
 * Una pregunta sin condicion siempre se muestra. Con condicion, solo si la
 * pregunta de la que depende tiene elegida la opcion exacta.
 *
 * Ojo con las de seleccion multiple: la respuesta es una lista, y basta con
 * que la opcion este dentro.
 */
export function isVisible(question: RenderQuestion, answers: Answers): boolean {
    if (question.condition === null) {
        return true
    }

    const respuesta = answers[question.condition.dependsOn]

    if (respuesta === null || respuesta === undefined) {
        return false
    }

    return Array.isArray(respuesta)
        ? respuesta.includes(question.condition.option)
        : respuesta === question.condition.option
}

/** Las preguntas que hay que contestar ahora mismo. */
export function visibleQuestions(
    questions: RenderQuestion[],
    answers: Answers,
): RenderQuestion[] {
    return questions.filter((q) => isVisible(q, answers))
}

/**
 * Que le falta a una respuesta para ser valida. RF-COL-016.
 *
 * Devuelve una CLAVE de traduccion, no un texto: quien la muestre decide el
 * idioma. Y null cuando esta bien.
 *
 * Esto NO es la validacion de verdad —esa la hace el servidor al recibir
 * (Fase 9)— sino la que evita que alguien avance sabiendo ya que va a
 * fallar. Duplicarla es deliberado: sin ella habria que preguntar al servidor
 * en cada pulsacion, y RNF-COL-009 pide reaccionar en menos de 100 ms.
 */
export function problemWith(
    question: RenderQuestion,
    answer: string | string[] | null | undefined,
): { key: string; values?: Record<string, number | string> } | null {
    const vacia =
        answer === null ||
        answer === undefined ||
        answer === '' ||
        (Array.isArray(answer) && answer.length === 0)

    if (vacia) {
        return question.isRequired ? { key: 'required' } : null
    }

    const limits = question.limits

    if (Array.isArray(answer)) {
        const min = limits.min_selections
        const max = limits.max_selections

        if (typeof min === 'number' && answer.length < min) {
            return { key: 'min_selections', values: { min } }
        }

        if (typeof max === 'number' && answer.length > max) {
            return { key: 'max_selections', values: { max } }
        }

        return null
    }

    if (question.type === 'short_text' || question.type === 'long_text') {
        const min = limits.min_length
        const max = limits.max_length

        if (typeof min === 'number' && answer.length < min) {
            return { key: 'min_length', values: { min } }
        }

        if (typeof max === 'number' && answer.length > max) {
            return { key: 'max_length', values: { max } }
        }
    }

    if (question.type === 'number') {
        const numero = Number(answer)

        if (Number.isNaN(numero)) {
            return { key: 'not_a_number' }
        }

        const min = limits.min
        const max = limits.max

        if (typeof min === 'number' && numero < min) {
            return { key: 'min', values: { min } }
        }

        if (typeof max === 'number' && numero > max) {
            return { key: 'max', values: { max } }
        }
    }

    if (question.type === 'date') {
        const min = limits.min_date
        const max = limits.max_date

        if (typeof min === 'string' && answer < min) {
            return { key: 'min_date', values: { min } }
        }

        if (typeof max === 'string' && answer > max) {
            return { key: 'max_date', values: { max } }
        }
    }

    return null
}

/**
 * Al ocultarse una pregunta, su respuesta se retira.
 *
 * Si alguien contesta "si", se le muestra la pregunta de seguimiento, la
 * contesta y luego cambia a "no", esa respuesta ya no tiene sentido: quedaria
 * guardada una contestacion a una pregunta que nunca se le hizo.
 */
export function pruneHidden(questions: RenderQuestion[], answers: Answers): Answers {
    const visibles = new Set(visibleQuestions(questions, answers).map((q) => q.ulid))
    const limpias: Answers = {}

    for (const [ulid, valor] of Object.entries(answers)) {
        if (visibles.has(ulid)) {
            limpias[ulid] = valor
        }
    }

    return limpias
}
