interface Props {
    value: string
    max: number
    id: string
}

/**
 * Contador de caracteres.
 *
 * Los limites ya los aplica el servidor —BuilderStateRequest— pero enterarse
 * cuando falla el autoguardado, con el texto ya escrito, es un mensaje de
 * error. Verlo mientras se escribe es prevencion de errores.
 *
 * Solo se anuncia al lector de pantalla cuando queda poco: leer "12 de 1000"
 * en cada tecla seria insoportable.
 */
export default function CharCount({ value, max, id }: Props) {
    const usados = value.length
    const cerca = usados > max * 0.9

    return (
        <p
            id={id}
            className={`char-count ${cerca ? 'char-count-near' : ''}`}
            aria-live={cerca ? 'polite' : 'off'}
        >
            {usados} / {max}
        </p>
    )
}
