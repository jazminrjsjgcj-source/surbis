import type { RenderQuestion } from '@/lib/renderer'
import { useTranslate } from '@/lib/translate'

interface Props {
    question: RenderQuestion
    value: string | string[] | null
    onChange: (value: string | string[] | null) => void
}

/**
 * El campo de una pregunta, segun su tipo. RF-COL-015 y 017.
 *
 * Un solo componente con un match y no nueve archivos: los nueve tipos
 * comparten estructura —etiqueta, ayuda, control, error— y separarlos
 * obligaria a repetir esa envoltura nueve veces.
 *
 * Los objetivos tactiles miden al menos 44 px (RNF-COL-007). Eso NO es un
 * detalle de estilo: en una tableta de ventanilla, un boton pequeno se falla
 * con el dedo, y quien contesta con prisa se va sin terminar.
 */
export default function QuestionField({ question, value, onChange }: Props) {
    const t = useTranslate()

    const descrito = question.help === null ? undefined : `${question.ulid}-help`

    /*
     * Las que se eligen pulsando: caritas, estrellas, una opcion, si/no.
     *
     * Botones y no <select>: en una pantalla tactil, desplegar una lista para
     * elegir entre cinco caras es mas trabajo que tocar la cara.
     */
    if (['smiley', 'rating', 'single_choice', 'yes_no'].includes(question.type)) {
        return (
            <fieldset className="renderer-field" aria-describedby={descrito}>
                <legend className="renderer-legend">{question.text}</legend>

                {question.help && (
                    <p id={descrito} className="hint">
                        {question.help}
                    </p>
                )}

                <div className={`renderer-choices renderer-choices-${question.type}`}>
                    {question.options.map((option) => (
                        <button
                            key={option.ulid}
                            type="button"
                            className="renderer-choice"
                            // aria-pressed y no solo el color: RNF-COL-007
                            // prohibe que el color sea el unico portador.
                            aria-pressed={value === option.ulid}
                            onClick={() => onChange(value === option.ulid ? null : option.ulid)}
                        >
                            {option.image && (
                                <img src={option.image.url} alt="" className="renderer-image" />
                            )}

                            {/*
                                El texto se OCULTA visualmente si la opcion es
                                "solo imagen", pero sigue en el marcado:
                                RNF-COL-011 exige nombre accesible aunque el
                                texto visible no este.
                            */}
                            <span className={option.display === 'image' ? 'sr-only' : ''}>
                                {option.label}
                            </span>
                        </button>
                    ))}
                </div>
            </fieldset>
        )
    }

    if (question.type === 'multiple_choice') {
        const elegidas = Array.isArray(value) ? value : []

        return (
            <fieldset className="renderer-field" aria-describedby={descrito}>
                <legend className="renderer-legend">{question.text}</legend>

                {question.help && (
                    <p id={descrito} className="hint">
                        {question.help}
                    </p>
                )}

                <div className="renderer-choices">
                    {question.options.map((option) => {
                        const marcada = elegidas.includes(option.ulid)

                        return (
                            <button
                                key={option.ulid}
                                type="button"
                                className="renderer-choice"
                                aria-pressed={marcada}
                                onClick={() =>
                                    onChange(
                                        marcada
                                            ? elegidas.filter((u) => u !== option.ulid)
                                            : [...elegidas, option.ulid],
                                    )
                                }
                            >
                                {option.image && (
                                    <img src={option.image.url} alt="" className="renderer-image" />
                                )}

                                <span className={option.display === 'image' ? 'sr-only' : ''}>
                                    {option.label}
                                </span>
                            </button>
                        )
                    })}
                </div>
            </fieldset>
        )
    }

    // Los que se escriben.
    const texto = typeof value === 'string' ? value : ''
    const id = `q-${question.ulid}`

    return (
        <div className="renderer-field">
            <label htmlFor={id} className="renderer-legend">
                {question.text}
            </label>

            {question.help && (
                <p id={descrito} className="hint">
                    {question.help}
                </p>
            )}

            {question.type === 'long_text' ? (
                <textarea
                    id={id}
                    className="input input-grow"
                    value={texto}
                    aria-describedby={descrito}
                    maxLength={
                        typeof question.limits.max_length === 'number'
                            ? question.limits.max_length
                            : undefined
                    }
                    onChange={(e) => onChange(e.target.value)}
                />
            ) : (
                <input
                    id={id}
                    type={
                        question.type === 'number'
                            ? 'number'
                            : question.type === 'date'
                              ? 'date'
                              : 'text'
                    }
                    className="input"
                    value={texto}
                    aria-describedby={descrito}
                    /*
                     * inputMode numerico en los numeros: en un movil abre el
                     * teclado de cifras en vez del alfabetico. Quien contesta
                     * de pie agradece no buscar los numeros.
                     */
                    inputMode={question.type === 'number' ? 'numeric' : undefined}
                    min={
                        typeof question.limits.min === 'number' ? question.limits.min : undefined
                    }
                    max={
                        typeof question.limits.max === 'number' ? question.limits.max : undefined
                    }
                    maxLength={
                        typeof question.limits.max_length === 'number'
                            ? question.limits.max_length
                            : undefined
                    }
                    onChange={(e) => onChange(e.target.value)}
                />
            )}

            {question.type === 'long_text' && typeof question.limits.max_length === 'number' && (
                <span className="hint">
                    {t('interface.renderer.characters', {
                        count: texto.length,
                        max: question.limits.max_length,
                    })}
                </span>
            )}
        </div>
    )
}
