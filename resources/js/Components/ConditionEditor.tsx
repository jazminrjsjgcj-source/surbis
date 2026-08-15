import { useState } from 'react'

import { availableSources, type Condition } from '@/lib/conditions'
import { useTranslate } from '@/lib/translate'

interface Option {
    ulid: string | null
    label: string
}

interface Question {
    ulid: string | null
    text: string
    options: Option[]
    condition: Condition | null
}

interface Props {
    questions: Question[]
    index: number
    readOnly: boolean
    onChange: (condition: Condition | null) => void
}

/**
 * "Mostrar esta pregunta solo si..." RF-AO-BLD-007.
 *
 * Plegable, y cerrado por defecto. En una encuesta de diez preguntas quiza
 * dos tienen condicion: mostrarle dos desplegables a las otras ocho es ruido
 * permanente por una funcion ocasional.
 */
export default function ConditionEditor({ questions, index, readOnly, onChange }: Props) {
    const t = useTranslate()
    const actual = questions[index]?.condition ?? null
    const [abierto, setAbierto] = useState(actual !== null)

    /*
     * Solo las preguntas ANTERIORES, y solo las que ya existen en el
     * servidor.
     *
     * Una pregunta recien anadida no tiene ulid todavia: no se puede
     * referenciar hasta que el autoguardado la haya escrito. Ofrecerla
     * crearia una condicion que apunta a nada.
     */
    const origenes = availableSources(questions, index).filter(
        (question) => question.options.length > 0,
    )

    const origen = origenes.find((question) => question.ulid === actual?.depends_on_ulid)

    if (!abierto) {
        return (
            <div className="mt-4">
                <button
                    type="button"
                    className="btn btn-ghost"
                    disabled={readOnly || origenes.length === 0}
                    onClick={() => setAbierto(true)}
                >
                    {t('interface.conditions.add')}
                </button>

                {origenes.length === 0 && (
                    <p className="hint mt-1">{t('interface.conditions.no_sources')}</p>
                )}
            </div>
        )
    }

    return (
        <fieldset className="mt-4 border-0 p-0">
            <legend className="text-sm font-semibold">{t('interface.conditions.title')}</legend>

            <div className="field">
                <label htmlFor={`cond-source-${index}`}>{t('interface.conditions.source')}</label>
                <select
                    id={`cond-source-${index}`}
                    className="input"
                    value={actual?.depends_on_ulid ?? ''}
                    disabled={readOnly}
                    onChange={(e) => {
                        // Al cambiar de pregunta origen, la opcion se limpia:
                        // una opcion de otra pregunta no tiene sentido y el
                        // servidor la rechazaria.
                        const ulid = e.target.value

                        onChange(ulid === '' ? null : { depends_on_ulid: ulid, option_ulid: '' })
                    }}
                >
                    <option value="">{t('interface.conditions.always')}</option>

                    {origenes.map((question, posicion) => (
                        <option key={question.ulid ?? posicion} value={question.ulid ?? ''}>
                            {posicion + 1}. {question.text || t('interface.builder.untitled')}
                        </option>
                    ))}
                </select>
            </div>

            {origen && (
                <div className="field">
                    <label htmlFor={`cond-option-${index}`}>
                        {t('interface.conditions.option')}
                    </label>
                    <select
                        id={`cond-option-${index}`}
                        className="input"
                        value={actual?.option_ulid ?? ''}
                        disabled={readOnly}
                        onChange={(e) =>
                            onChange({
                                depends_on_ulid: origen.ulid ?? '',
                                option_ulid: e.target.value,
                            })
                        }
                    >
                        <option value="">{t('interface.conditions.choose_option')}</option>

                        {origen.options.map((option, posicion) => (
                            <option key={option.ulid ?? posicion} value={option.ulid ?? ''}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            {!readOnly && (
                <button
                    type="button"
                    className="btn btn-ghost btn-danger"
                    onClick={() => {
                        onChange(null)
                        setAbierto(false)
                    }}
                >
                    {t('interface.conditions.remove')}
                </button>
            )}
        </fieldset>
    )
}
