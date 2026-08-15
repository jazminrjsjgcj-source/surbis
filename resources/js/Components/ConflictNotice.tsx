import { useTranslate } from '@/lib/translate'

interface Props {
    actual: number
    onDiscardMine: () => void
    onRetry: () => void
}

/**
 * Conflicto: otra persona guardo mientras editabas.
 *
 * Dos salidas, y las dos dicen que se pierde. La tercera —comparar ambas
 * versiones y elegir por partes— es lo correcto y es una tarea aparte:
 * construir un comparador ahora seria mucho trabajo para un caso que en una
 * organizacion municipal sera raro.
 *
 * role="alert" porque esto SI interrumpe: el autoguardado esta detenido y la
 * persona tiene que decidir algo.
 */
export default function ConflictNotice({ actual, onDiscardMine, onRetry }: Props) {
    const t = useTranslate()

    return (
        <div className="alert alert-error mb-4" role="alert">
            <p className="alert-title">{t('interface.builder.conflict_title')}</p>
            <p>{t('interface.builder.conflict_help')}</p>

            <div className="actions">
                <button type="button" className="btn btn-ghost" onClick={onDiscardMine}>
                    {t('interface.builder.conflict_discard')}
                </button>

                {/* "Sobrescribir" no se salta la comprobacion: relee y
                    reintenta con el numero nuevo. Si mientras tanto hubo una
                    tercera edicion, vuelve a dar conflicto, y es correcto. */}
                <button type="button" className="btn btn-primary" onClick={() => onRetry()}>
                    {t('interface.builder.conflict_overwrite')}
                </button>
            </div>

            <p className="hint mt-2">
                {t('interface.builder.conflict_version', { number: actual })}
            </p>
        </div>
    )
}
