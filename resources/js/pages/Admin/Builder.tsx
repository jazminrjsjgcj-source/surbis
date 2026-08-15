import { Head, Link } from '@inertiajs/react'
import { useCallback, useState } from 'react'

import CharCount from '@/Components/CharCount'
import AdminShell from '@/Layouts/AdminShell'
import ConditionEditor from '@/Components/ConditionEditor'
import ConflictNotice from '@/Components/ConflictNotice'
import OptionEditor from '@/Components/OptionEditor'
import QuestionList from '@/Components/QuestionList'
import SaveIndicator from '@/Components/SaveIndicator'
import { type Condition, dependentsOf, movementBreaksCondition } from '@/lib/conditions'
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
    condition: Condition | null
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
    importUrl: string
}

const MAX_TEXT = 1000
const MAX_HELP = 1000

/**
 * Constructor en dos columnas.
 *
 * La version anterior lo mostraba todo desplegado y era confusa: dos grupos
 * de botones identicos —uno de opcion, otro de pregunta— sin nada que dijera
 * cual actuaba sobre que.
 *
 * Aqui la lista manda el orden y el panel edita lo seleccionado. Los dos
 * grupos dejan de confundirse porque estan en columnas distintas, no porque
 * cambien de color.
 */
export default function Builder({ survey, version, readOnly, questionTypes, importUrl }: Props) {
    const t = useTranslate()
    const [questions, setQuestions] = useState<Question[]>(version.questions)
    const [selected, setSelected] = useState(0)

    const { state, conflict, rejection, lastSavedAt, saveNow, retryWithServerVersion } =
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

    const actual = questions[selected]

    function update(cambios: Partial<Question>): void {
        setQuestions((actuales) =>
            actuales.map((pregunta, i) => (i === selected ? { ...pregunta, ...cambios } : pregunta)),
        )
    }

    function add(): void {
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
                condition: null,
            },
        ])

        // La nueva queda seleccionada: anadir para luego tener que buscarla
        // en la lista seria un paso de mas cada vez.
        setSelected(questions.length)
    }

    function duplicate(): void {
        setQuestions((actuales) => {
            const original = actuales[selected]

            if (!original) {
                return actuales
            }

            // ulid a null: es una pregunta NUEVA. Copiar el ulid haria que el
            // servidor actualizara la original en lugar de crear otra.
            /*
             * La copia NO hereda la condicion.
             *
             * Duplicar coloca la copia justo DESPUES de la original, asi que
             * heredar su condicion daria dos preguntas que aparecen y
             * desaparecen juntas: casi nunca es lo que se quiere, y quitarla
             * despues es mas facil que descubrirla.
             */
            const copia: Question = {
                ...original,
                ulid: null,
                condition: null,
                options: original.options.map((opcion) => ({ ...opcion, ulid: null })),
            }

            return [...actuales.slice(0, selected + 1), copia, ...actuales.slice(selected + 1)]
        })

        setSelected(selected + 1)
    }

    /*
     * No se borra una pregunta de la que algo depende.
     *
     * Decision del area usuaria: las condiciones NUNCA se eliminan solas.
     * Borrar la pregunta origen y retirar en silencio la condicion de otra
     * seria perder trabajo ajeno sin avisar.
     *
     * El boton se deshabilita, asi que esto no deberia llegar a ejecutarse.
     * Esta igualmente porque deshabilitar un boton no es una proteccion.
     */
    const dependientes = dependentsOf(questions, actual?.ulid ?? null)

    function remove(): void {
        if (dependientes.length > 0) {
            return
        }

        setQuestions((actuales) => actuales.filter((_, i) => i !== selected))
        setSelected((anterior) => Math.max(0, anterior - 1))
    }

    /**
     * Mover con teclado. RNF-AO-BLD-001 exige alternativa a arrastrar y
     * soltar: sin ella, quien no usa raton no puede reordenar, y arrastrar es
     * ademas dificil con temblor o en una pantalla tactil pequena.
     */
    function move(indice: number, direccion: -1 | 1): void {
        const destino = indice + direccion

        if (destino < 0 || destino >= questions.length) {
            return
        }

        /*
         * Un movimiento que dejaria una condicion mirando hacia delante se
         * rechaza. RF-AO-BLD-007 y decision del area usuaria.
         *
         * Quien contesta llegaria a una pregunta sin haber respondido de que
         * depende, asi que la pregunta no podria decidir si mostrarse.
         */
        if (movementBreaksCondition(questions, indice, direccion)) {
            return
        }

        setQuestions((actuales) => {
            const copia = [...actuales]
            const [movida] = copia.splice(indice, 1)

            if (movida) {
                copia.splice(destino, 0, movida)
            }

            return copia
        })

        // La seleccion sigue a la pregunta movida: si se quedara quieta, el
        // panel pasaria a editar otra sin que nadie lo pidiera.
        setSelected(destino)
    }

    return (
        <AdminShell>
            <Head title={t('interface.builder.title')} />

            <div className="page-header flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1>{survey.name}</h1>
                    <p className="hint mt-1">
                        {t('interface.builder.version', { number: version.number })}
                    </p>
                </div>

                {!readOnly && (
                    <SaveIndicator state={state} rejection={rejection} lastSavedAt={lastSavedAt} onSaveNow={saveNow} />
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
                            <div className="actions justify-center">
                                <button type="button" className="btn btn-primary" onClick={add}>
                                    {t('interface.builder.add')}
                                </button>

                                {/* Escribir doce preguntas de una vez es mucho
                                    mas rapido que anadirlas de una en una, y
                                    aqui es donde se nota: la encuesta esta
                                    vacia. */}
                                <Link href={importUrl} className="btn btn-ghost">
                                    {t('interface.import.link')}
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            ) : (
                <div className="builder">
                    <div className="card card-pad">
                        <QuestionList
                            questions={questions}
                            selected={selected}
                            readOnly={readOnly}
                            onSelect={setSelected}
                            onMove={move}
                            canMove={(indice, direccion) =>
                                !movementBreaksCondition(questions, indice, direccion)
                            }
                        />

                        {!readOnly && (
                            <button type="button" className="btn btn-primary btn-block mt-3" onClick={add}>
                                {t('interface.builder.add')}
                            </button>
                        )}
                    </div>

                    {actual && (
                        <div className="card card-pad">
                            <div className="field">
                                <label htmlFor="text">{t('interface.builder.question_text')}</label>

                                {/* textarea y no input: un texto largo en una
                                    linea obliga a desplazarse dentro del
                                    campo para releer lo escrito. */}
                                <textarea
                                    id="text"
                                    className="input input-grow"
                                    value={actual.text}
                                    maxLength={MAX_TEXT}
                                    disabled={readOnly}
                                    aria-describedby="text-count"
                                    onChange={(e) => update({ text: e.target.value })}
                                />
                                <CharCount id="text-count" value={actual.text} max={MAX_TEXT} />
                            </div>

                            <div className="field">
                                <label htmlFor="help">{t('interface.builder.question_help')}</label>
                                <textarea
                                    id="help"
                                    className="input input-grow"
                                    value={actual.help ?? ''}
                                    maxLength={MAX_HELP}
                                    disabled={readOnly}
                                    aria-describedby="help-hint help-count"
                                    onChange={(e) => update({ help: e.target.value || null })}
                                />
                                <span id="help-hint" className="hint">
                                    {t('interface.builder.question_help_hint')}
                                </span>
                                <CharCount id="help-count" value={actual.help ?? ''} max={MAX_HELP} />
                            </div>

                            <div className="field">
                                <label htmlFor="type">{t('interface.builder.question_type')}</label>
                                <select
                                    id="type"
                                    className="input"
                                    value={actual.type}
                                    disabled={readOnly}
                                    onChange={(e) => {
                                        const nuevo = tipoDe(e.target.value)

                                        update({
                                            type: e.target.value,
                                            // El servidor las borra igual al
                                            // cambiar a un tipo sin opciones.
                                            // Verlas desaparecer un segundo
                                            // despues seria desconcertante.
                                            options: nuevo?.has_options ? actual.options : [],
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
                                    checked={actual.is_required}
                                    disabled={readOnly}
                                    onChange={(e) => update({ is_required: e.target.checked })}
                                />
                                {t('interface.builder.required')}
                            </label>

                            {tipoDe(actual.type)?.has_options && (
                                <OptionEditor
                                    questionIndex={selected}
                                    options={actual.options}
                                    isScored={tipoDe(actual.type)?.is_scored ?? false}
                                    readOnly={readOnly}
                                    onChange={(options) => update({ options })}
                                />
                            )}

                            <ConditionEditor
                                questions={questions}
                                index={selected}
                                readOnly={readOnly}
                                onChange={(condition) => update({ condition })}
                            />

                            {/*
                                Por que no se puede borrar, con las preguntas
                                que lo impiden. "No se puede" a secas obliga a
                                revisarlas una por una.
                            */}
                            {dependientes.length > 0 && (
                                <p className="hint mt-4" role="status">
                                    {t('interface.conditions.blocked_by', {
                                        positions: dependientes.join(', '),
                                    })}
                                </p>
                            )}

                            {!readOnly && (
                                <div className="actions border-line mt-4 border-t pt-3">
                                    <button type="button" className="btn btn-ghost" onClick={duplicate}>
                                        {t('interface.builder.duplicate')}
                                    </button>

                                    <button
                                        type="button"
                                        className="btn btn-ghost btn-danger"
                                        disabled={dependientes.length > 0}
                                        onClick={remove}
                                    >
                                        {t('interface.builder.remove')}
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}
        </AdminShell>
    )
}
