import { Head } from '@inertiajs/react'
import { useCallback, useState } from 'react'

import ConflictNotice from '@/Components/ConflictNotice'
import SaveIndicator from '@/Components/SaveIndicator'
import { useTranslate } from '@/lib/translate'
import { useBuilderPersistence } from '@/lib/useBuilderPersistence'

interface Option {
    ulid: string | null
    label: string
    value: string
    score: number | null
    display: string
    appearance: Record<string, unknown> | null
}

interface Question {
    ulid: string | null
    type: string
    text: string
    help: string | null
    is_required: boolean
    limits: Record<string, unknown>
    options: Option[]
}

interface QuestionTypeInfo {
    value: string
    has_options: boolean
    is_scored: boolean
    limit_keys: string[]
}

interface Props {
    survey: { ulid: string; name: string }
    version: { ulid: string; number: number; lock_version: number; questions: Question[] }
    readOnly: boolean
    questionTypes: QuestionTypeInfo[]
}

export default function Builder({ survey, version, readOnly, questionTypes }: Props) {
    const t = useTranslate()
    const [questions, setQuestions] = useState<Question[]>(version.questions)

    const { state, conflict, lastSavedAt, saveNow, retryWithServerVersion } =
        useBuilderPersistence({
            versionUlid: version.ulid,
            initialLock: version.lock_version,
            endpoint: `/admin/encuestas/${survey.ulid}/constructor`,
            readOnly,
            value: questions,
        })

    const tipoDe = useCallback(
        (valor: string): QuestionTypeInfo | undefined =>
            questionTypes.find((tipo) => tipo.value === valor),
        [questionTypes],
    )

    function addQuestion(): void {
        setQuestions((actuales) => [
            ...actuales,
            {
                ulid: null,
                type: 'single_choice',
                text: '',
                help: null,
                is_required: false,
                limits: {},
                options: [],
            },
        ])
    }

    function updateQuestion(indice: number, cambios: Partial<Question>): void {
        setQuestions((actuales) =>
            actuales.map((pregunta, i) => (i === indice ? { ...pregunta, ...cambios } : pregunta)),
        )
    }

    function removeQuestion(indice: number): void {
        setQuestions((actuales) => actuales.filter((_, i) => i !== indice))
    }

    function duplicateQuestion(indice: number): void {
        setQuestions((actuales) => {
            const original = actuales[indice]

            if (!original) {
                return actuales
            }

            // ulid a null: es una pregunta NUEVA. Copiar el ulid haria que el
            // servidor actualizara la original en lugar de crear otra.
            const copia: Question = {
                ...original,
                ulid: null,
                options: original.options.map((opcion) => ({ ...opcion, ulid: null })),
            }

            return [...actuales.slice(0, indice + 1), copia, ...actuales.slice(indice + 1)]
        })
    }

    /**
     * Mover con teclado. RNF-AO-BLD-001 exige alternativa a arrastrar y
     * soltar: sin ella, quien no usa raton no puede reordenar, y arrastrar es
     * ademas dificil con temblor o con pantalla tactil pequena.
     */
    function moveQuestion(indice: number, direccion: -1 | 1): void {
        setQuestions((actuales) => {
            const destino = indice + direccion

            if (destino < 0 || destino >= actuales.length) {
                return actuales
            }

            const copia = [...actuales]
            const [movida] = copia.splice(indice, 1)

            if (movida) {
                copia.splice(destino, 0, movida)
            }

            return copia
        })
    }

    return (
        <>
            <Head title={t('interface.builder.title')} />

            <div className="shell-content">
                <div className="page-header flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1>{survey.name}</h1>
                        <p className="hint mt-1">
                            {t('interface.builder.version', { number: version.number })}
                        </p>
                    </div>

                    {!readOnly && (
                        <SaveIndicator state={state} lastSavedAt={lastSavedAt} onSaveNow={saveNow} />
                    )}
                </div>

                {readOnly && (
                    <div className="alert alert-neutral mb-4" role="status">
                        {t('interface.builder.read_only')}
                    </div>
                )}

                {conflict && (
                    <ConflictNotice
                        actual={conflict.actual}
                        onDiscardMine={() => window.location.reload()}
                        onRetry={() => retryWithServerVersion(conflict.actual)}
                    />
                )}

                {questions.length === 0 ? (
                    <div className="card card-pad">
                        <div className="empty">
                            <h2>{t('interface.builder.empty_title')}</h2>
                            <p>{t('interface.builder.empty_help')}</p>

                            {!readOnly && (
                                <button type="button" className="btn btn-primary" onClick={addQuestion}>
                                    {t('interface.builder.add')}
                                </button>
                            )}
                        </div>
                    </div>
                ) : (
                    <ol className="flex flex-col gap-3">
                        {questions.map((pregunta, indice) => (
                            <li key={pregunta.ulid ?? `nueva-${indice}`} className="card card-pad">
                                <div className="field">
                                    <label htmlFor={`text-${indice}`}>
                                        {t('interface.builder.question_text')}
                                    </label>
                                    <input
                                        id={`text-${indice}`}
                                        type="text"
                                        className="input"
                                        value={pregunta.text}
                                        disabled={readOnly}
                                        onChange={(e) => updateQuestion(indice, { text: e.target.value })}
                                    />
                                </div>

                                <div className="field">
                                    <label htmlFor={`type-${indice}`}>
                                        {t('interface.builder.question_type')}
                                    </label>
                                    <select
                                        id={`type-${indice}`}
                                        className="input"
                                        value={pregunta.type}
                                        disabled={readOnly}
                                        onChange={(e) => {
                                            const nuevo = tipoDe(e.target.value)

                                            updateQuestion(indice, {
                                                type: e.target.value,
                                                // Si el tipo nuevo no admite
                                                // opciones, se vacian aqui
                                                // tambien: el servidor las
                                                // borra igual, y verlas
                                                // desaparecer al guardar
                                                // seria desconcertante.
                                                options: nuevo?.has_options ? pregunta.options : [],
                                            })
                                        }}
                                    >
                                        {questionTypes.map((tipo) => (
                                            <option key={tipo.value} value={tipo.value}>
                                                {t(`interface.builder.type_${tipo.value}`)}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <label className="text-ink-muted flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={pregunta.is_required}
                                        disabled={readOnly}
                                        onChange={(e) =>
                                            updateQuestion(indice, { is_required: e.target.checked })
                                        }
                                    />
                                    {t('interface.builder.required')}
                                </label>

                                {!readOnly && (
                                    <div className="actions">
                                        <button
                                            type="button"
                                            className="btn btn-ghost"
                                            onClick={() => moveQuestion(indice, -1)}
                                            disabled={indice === 0}
                                        >
                                            {t('interface.builder.move_up')}
                                        </button>

                                        <button
                                            type="button"
                                            className="btn btn-ghost"
                                            onClick={() => moveQuestion(indice, 1)}
                                            disabled={indice === questions.length - 1}
                                        >
                                            {t('interface.builder.move_down')}
                                        </button>

                                        <button
                                            type="button"
                                            className="btn btn-ghost"
                                            onClick={() => duplicateQuestion(indice)}
                                        >
                                            {t('interface.builder.duplicate')}
                                        </button>

                                        <button
                                            type="button"
                                            className="btn btn-ghost"
                                            onClick={() => removeQuestion(indice)}
                                        >
                                            {t('interface.builder.remove')}
                                        </button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ol>
                )}

                {!readOnly && questions.length > 0 && (
                    <button type="button" className="btn btn-primary mt-4" onClick={addQuestion}>
                        {t('interface.builder.add')}
                    </button>
                )}
            </div>
        </>
    )
}
