import { useTranslate } from '@/lib/translate'

interface Question {
    ulid: string | null
    type: string
    text: string
}

interface Props {
    questions: Question[]
    selected: number
    readOnly: boolean
    onSelect: (index: number) => void
    onMove: (index: number, direction: -1 | 1) => void
}

/**
 * La lista de preguntas. Manda el orden.
 *
 * Separar la lista de la edicion resuelve la confusion de la version
 * anterior: los botones de reordenar viven aqui y los de editar alli, asi que
 * ya no se parecen porque estan en sitios distintos, no porque cambien de
 * color.
 */
export default function QuestionList({
    questions,
    selected,
    readOnly,
    onSelect,
    onMove,
}: Props) {
    const t = useTranslate()

    return (
        <nav aria-label={t('interface.builder.list_label')}>
            <ol className="builder-list">
                {questions.map((pregunta, indice) => (
                    <li key={pregunta.ulid ?? `nueva-${indice}`}>
                        <button
                            type="button"
                            className="builder-item"
                            aria-current={indice === selected}
                            onClick={() => onSelect(indice)}
                        >
                            <span className="builder-item-number">{indice + 1}</span>

                            <span className="min-w-0">
                                {/* title con el texto entero: cortar a dos
                                    lineas oculta informacion, y esto la
                                    devuelve sin ocupar espacio. */}
                                <span
                                    className="builder-item-text"
                                    title={pregunta.text || t('interface.builder.untitled')}
                                >
                                    {pregunta.text || t('interface.builder.untitled')}
                                </span>

                                <span className="builder-item-type block">
                                    {t(`interface.builder.type_${pregunta.type}`)}
                                </span>
                            </span>
                        </button>

                        {!readOnly && indice === selected && (
                            <div className="actions ms-6">
                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    onClick={() => onMove(indice, -1)}
                                    disabled={indice === 0}
                                >
                                    {t('interface.builder.move_up')}
                                </button>

                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    onClick={() => onMove(indice, 1)}
                                    disabled={indice === questions.length - 1}
                                >
                                    {t('interface.builder.move_down')}
                                </button>
                            </div>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    )
}
