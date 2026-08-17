import CharCount from '@/Components/CharCount'
import MediaPicker, { type MediaOption } from '@/Components/MediaPicker'
import { useState } from 'react'

import { useTranslate } from '@/lib/translate'

interface Option {
    ulid: string | null
    label: string
    value: string
    score: number | null
    display: string
    media_ulid: string | null
    appearance: Record<string, unknown> | null
}

interface Props {
    questionIndex: number
    options: Option[]
    isScored: boolean
    readOnly: boolean
    media: MediaOption[]
    mediaUploadUrl?: string
    onChange: (options: Option[]) => void
}

const MAX_LABEL = 255
const MAX_VALUE = 255

export default function OptionEditor({
    questionIndex,
    options,
    isScored,
    readOnly,
    media, mediaUploadUrl,
    onChange,
}: Props) {
    const t = useTranslate()
    const [eligiendoImagen, setEligiendoImagen] = useState<number | null>(null)

    /*
     * Valores repetidos, senalados mientras se escribe.
     *
     * El servidor los rechaza y la base tambien —esa es la garantia— pero
     * descubrirlo un segundo despues, cuando el autoguardado falla, obliga a
     * recordar que se estaba escribiendo.
     */
    const repetidos = new Set(
        options
            .map((opcion) => opcion.value.trim())
            .filter((valor, i, todos) => valor !== '' && todos.indexOf(valor) !== i),
    )

    function update(indice: number, cambios: Partial<Option>): void {
        onChange(options.map((opcion, i) => (i === indice ? { ...opcion, ...cambios } : opcion)))
    }

    function add(): void {
        onChange([
            ...options,
            {
                ulid: null,
                label: '',
                value: '',
                score: isScored ? 0 : null,
                display: 'text',
                media_ulid: null,
                appearance: null,
            },
        ])
    }

    function move(indice: number, direccion: -1 | 1): void {
        const destino = indice + direccion

        if (destino < 0 || destino >= options.length) {
            return
        }

        const copia = [...options]
        const [movida] = copia.splice(indice, 1)

        if (movida) {
            copia.splice(destino, 0, movida)
        }

        onChange(copia)
    }

    return (
        <fieldset className="mt-4 border-0 p-0">
            <legend className="text-sm font-semibold">{t('interface.builder.options')}</legend>

            {options.length === 0 && (
                <p className="hint mt-1">{t('interface.builder.options_empty')}</p>
            )}

            {options.map((opcion, indice) => {
                const id = `q${questionIndex}-o${indice}`
                const duplicado = opcion.value.trim() !== '' && repetidos.has(opcion.value.trim())

                return (
                    <div key={opcion.ulid ?? `nueva-${indice}`} className="builder-option">
                        <div className="builder-option-grid">
                            <div className="field">
                                <label htmlFor={`${id}-label`}>
                                    {t('interface.builder.option_label')}
                                </label>
                                <input
                                    id={`${id}-label`}
                                    type="text"
                                    className="input"
                                    value={opcion.label}
                                    maxLength={MAX_LABEL}
                                    disabled={readOnly}
                                    aria-describedby={`${id}-label-count`}
                                    onChange={(e) => update(indice, { label: e.target.value })}
                                />
                                <CharCount
                                    id={`${id}-label-count`}
                                    value={opcion.label}
                                    max={MAX_LABEL}
                                />
                            </div>

                            <div className="field">
                                <label htmlFor={`${id}-value`}>
                                    {t('interface.builder.option_value')}
                                </label>
                                <input
                                    id={`${id}-value`}
                                    type="text"
                                    className="input"
                                    value={opcion.value}
                                    maxLength={MAX_VALUE}
                                    disabled={readOnly}
                                    aria-invalid={duplicado ? true : undefined}
                                    aria-describedby={duplicado ? `${id}-error` : `${id}-hint`}
                                    onChange={(e) => update(indice, { value: e.target.value })}
                                />

                                {duplicado ? (
                                    <span id={`${id}-error`} className="error">
                                        {t('interface.builder.option_duplicate')}
                                    </span>
                                ) : (
                                    <span id={`${id}-hint`} className="hint">
                                        {t('interface.builder.option_value_hint')}
                                    </span>
                                )}
                            </div>

                            {isScored ? (
                                <div className="field">
                                    <label htmlFor={`${id}-score`}>
                                        {t('interface.builder.option_score')}
                                    </label>
                                    <input
                                        id={`${id}-score`}
                                        type="number"
                                        className="input"
                                        value={opcion.score ?? 0}
                                        disabled={readOnly}
                                        onChange={(e) =>
                                            update(indice, { score: Number(e.target.value) })
                                        }
                                    />
                                </div>
                            ) : (
                                <div />
                            )}

                            <div className="field">
                                <label htmlFor={`${id}-display`}>
                                    {t('interface.builder.option_display')}
                                </label>
                                <select
                                    id={`${id}-display`}
                                    className="input"
                                    value={opcion.display}
                                    disabled={readOnly}
                                    onChange={(e) => update(indice, { display: e.target.value })}
                                >
                                    <option value="text">
                                        {t('interface.builder.display_text')}
                                    </option>

                                    {/* Ya existe la biblioteca (Fase 5), asi
                                        que dejan de estar deshabilitadas. */}
                                    <option value="image">
                                        {t('interface.builder.display_image')}
                                    </option>
                                    <option value="image_and_text">
                                        {t('interface.builder.display_image_and_text')}
                                    </option>
                                </select>

                                {/*
                                    El boton solo aparece si el tipo de
                                    presentacion necesita imagen. Ofrecerlo
                                    siempre invitaria a elegir una que no se
                                    va a mostrar.
                                */}
                                {opcion.display !== 'text' && (
                                    <button
                                        type="button"
                                        className="btn btn-ghost mt-2"
                                        disabled={readOnly}
                                        onClick={() => setEligiendoImagen(indice)}
                                    >
                                        {opcion.media_ulid === null
                                            ? t('interface.media.choose')
                                            : t('interface.media.change')}
                                    </button>
                                )}

                                {/*
                                    Y se avisa si falta. Publicar con una
                                    opcion "solo imagen" sin imagen deja un
                                    hueco invisible en el quiosco;
                                    PublicationChecklist lo impide, pero
                                    enterarse aqui evita llegar hasta alli.
                                */}
                                {opcion.display !== 'text' && opcion.media_ulid === null && (
                                    <span className="error">
                                        {t('interface.media.missing')}
                                    </span>
                                )}
                            </div>
                        </div>

                        {!readOnly && (
                            <div className="actions">
                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    onClick={() => move(indice, -1)}
                                    disabled={indice === 0}
                                >
                                    {t('interface.builder.move_up')}
                                </button>

                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    onClick={() => move(indice, 1)}
                                    disabled={indice === options.length - 1}
                                >
                                    {t('interface.builder.move_down')}
                                </button>

                                <button
                                    type="button"
                                    className="btn btn-ghost btn-danger"
                                    onClick={() => onChange(options.filter((_, i) => i !== indice))}
                                >
                                    {t('interface.builder.option_remove')}
                                </button>
                            </div>
                        )}
                    </div>
                )
            })}

            {!readOnly && (
                <button type="button" className="btn btn-ghost mt-3" onClick={add}>
                    {t('interface.builder.option_add')}
                </button>
            )}

            <MediaPicker
                open={eligiendoImagen !== null}
                media={media}
                uploadUrl={mediaUploadUrl}
                selected={
                    eligiendoImagen === null
                        ? null
                        : (options[eligiendoImagen]?.media_ulid ?? null)
                }
                onSelect={(ulid) => {
                    if (eligiendoImagen !== null) {
                        update(eligiendoImagen, { media_ulid: ulid })
                    }
                }}
                onClose={() => setEligiendoImagen(null)}
            />
        </fieldset>
    )
}
