import { useTranslate } from '@/lib/translate'

interface Props {
    errors: Record<string, string>
}

/**
 * Resumen de errores.
 *
 * Va antes del formulario y con role="alert" para que un lector de pantalla
 * lo anuncie. Sin el, quien no ve la pantalla no se entera de que el envio
 * fallo. RNF-AUT-004 y RNF-GEN-006.
 *
 * El titulo nombra el estado en texto: el color no es el unico portador.
 */
export default function ErrorSummary({ errors }: Props) {
    const t = useTranslate()
    const list = Object.values(errors)

    if (list.length === 0) {
        return null
    }

    return (
        <div className="alert alert-error mb-4" role="alert">
            <p className="alert-title">{t('interface.errors.summary')}</p>

            <ul className="ps-4">
                {list.map((message) => (
                    <li key={message}>{message}</li>
                ))}
            </ul>
        </div>
    )
}
