import { useTranslate } from '@/lib/translate'

interface Option {
    ulid: string | null
    label: string
    value: string
    score: number | null
    display: string
    appearance: Record<string, unknown> | null
}

interface Props {
    questionIndex: number
    options: Option[]
    isScored: boolean
    readOnly: boolean
    onChange: (options: Option[]) => void
}

/**
 * Opciones de una pregunta. RF-AO-BLD-003, 005 y 010.
 *
 * Sin esto, el constructor permite crear una pregunta de tipo "Una opcion"
 * sin opciones: una pregunta que no se puede contestar.
 */
export default function OptionEditor({
    questionIndex,
    options,
    isScored,
    readOnly,
    onChange,
}: Props) {
    const t = useTranslate()

    /*
     * Valores repetidos, senalados aqui y no solo al guardar.
     *
     * El servidor los rechaza igual —y la base tambien, que es la garantia de
     * verdad— pero descubrirlo un segundo despues, cuando el autoguardado
     * falla, obliga a recordar que se estaba escribiendo. Verlo mientras se
     * escribe es prevencion de errores; verlo despues es un mensaje de error.
     */
    const repetidos = new Set(
        options
            .map((opcion) => opcion.value.trim())
            .filter((valor, indice, todos) => valor !== '' && todos.indexOf(valor) !== indice),
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
                appearance: null,
            },
        ])
    }

    function remove(indice: number): void {
        onChange(options.filter((_, i) => i !== indice))
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
        <fieldset className="mt-3 border-0 p-0">
            <legend className="text-sm font-semibold">{t('interface.builder.options')}</legend>

            {options.length === 0 && (
                <p className="hint mt-1">{t('interface.builder.options_empty')}</p>
            )}

            {options.map((opcion, indice) => {
                const idBase = `q${questionIndex}-o${indice}`
                const duplicado = opcion.value.trim() !== '' && repetidos.has(opcion.value.trim())

                return (
                    <div key={opcion.ulid ?? `nueva-${indice}`} className="panel">
                        <div className="flex flex-wrap gap-3">
                            <div className="field toolbar-grow">
                                {/* La etiqueta es obligatoria SIEMPRE: es el
                                    nombre accesible de RF-AO-BLD-005, tambien
                                    cuando la opcion se muestra solo como
                                    imagen. */}
                                <label htmlFor={`${idBase}-label`}>
                                    {t('interface.builder.option_label')}
                                </label>
                                <input
                                    id={`${idBase}-label`}
                                    type="text"
                                    className="input"
                                    value={opcion.label}
                                    disabled={readOnly}
                                    onChange={(e) => update(indice, { label: e.target.value })}
                                />
                            </div>

                            <div className="field">
                                <label htmlFor={`${idBase}-value`}>
                                    {t('interface.builder.option_value')}
                                </label>
                                <input
                                    id={`${idBase}-value`}
                                    type="text"
                                    className="input"
                                    value={opcion.value}
                                    disabled={readOnly}
                                    aria-invalid={duplicado ? true : undefined}
                                    aria-describedby={duplicado ? `${idBase}-error` : `${idBase}-hint`}
                                    onChange={(e) => update(indice, { value: e.target.value })}
                                />

                                {duplicado ? (
                                    <span id={`${idBase}-error`} className="error">
                                        {t('interface.builder.option_duplicate')}
                                    </span>
                                ) : (
                                    <span id={`${idBase}-hint`} className="hint">
                                        {t('interface.builder.option_value_hint')}
                                    </span>
                                )}
                            </div>

                            {/* La puntuacion solo aparece si el tipo puntua.
                                Pedirla en un texto libre seria un campo que no
                                significa nada. */}
                            {isScored && (
                                <div className="field">
                                    <label htmlFor={`${idBase}-score`}>
                                        {t('interface.builder.option_score')}
                                    </label>
                                    <input
                                        id={`${idBase}-score`}
                                        type="number"
                                        className="input"
                                        value={opcion.score ?? 0}
                                        disabled={readOnly}
                                        onChange={(e) =>
                                            update(indice, { score: Number(e.target.value) })
                                        }
                                    />
                                </div>
                            )}

                            <div className="field">
                                <label htmlFor={`${idBase}-display`}>
                                    {t('interface.builder.option_display')}
                                </label>
                                <select
                                    id={`${idBase}-display`}
                                    className="input"
                                    value={opcion.display}
                                    disabled={readOnly}
                                    onChange={(e) => update(indice, { display: e.target.value })}
                                >
                                    <option value="text">
                                        {t('interface.builder.display_text')}
                                    </option>
                                    <option value="image">
                                        {t('interface.builder.display_image')}
                                    </option>
                                    <option value="image_and_text">
                                        {t('interface.builder.display_image_and_text')}
                                    </option>
                                </select>

                                {/* Las dos que necesitan imagen se pueden
                                    elegir, pero la imagen llega en la Fase 5.
                                    Decirlo aqui evita que alguien las
                                    configure y no entienda por que no se ve
                                    nada. */}
                                {opcion.display !== 'text' && (
                                    <span className="hint">
                                        {t('interface.builder.display_pending')}
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
                                    className="btn btn-ghost"
                                    onClick={() => remove(indice)}
                                >
                                    {t('interface.builder.option_remove')}
                                </button>
                            </div>
                        )}
                    </div>
                )
            })}

            {!readOnly && (
                <button type="button" className="btn btn-ghost mt-2" onClick={add}>
                    {t('interface.builder.option_add')}
                </button>
            )}
        </fieldset>
    )
}
