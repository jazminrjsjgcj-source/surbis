import type { SaveState } from '@/lib/useBuilderPersistence'
import { useTranslate } from '@/lib/translate'

interface Props {
    state: SaveState
    lastSavedAt: number | null
    onSaveNow: () => void
}

/**
 * Estado de persistencia, permanente y discreto.
 *
 * Decision del area usuaria: sin notificaciones emergentes para los guardados
 * correctos. Eso convierte a este indicador en el UNICO canal, asi que tiene
 * que ser legible por lector de pantalla.
 *
 * role="status" con aria-live="polite": se anuncia sin interrumpir. Con
 * "assertive", un cambio de estado cada segundo seria insoportable.
 */
export default function SaveIndicator({ state, lastSavedAt, onSaveNow }: Props) {
    const t = useTranslate()

    const etiqueta: Record<SaveState, string> = {
        synced: t('interface.builder.state_synced'),
        pending: t('interface.builder.state_pending'),
        saving: t('interface.builder.state_saving'),
        local: t('interface.builder.state_local'),
        error: t('interface.builder.state_error'),
        conflict: t('interface.builder.state_conflict'),
    }

    // El color acompana; el texto informa. ANEXO 1 seccion 47.
    const tono: Record<SaveState, string> = {
        synced: 'text-positive-text',
        pending: 'text-ink-muted',
        saving: 'text-ink-muted',
        local: 'text-neutral-text',
        error: 'text-negative-text',
        conflict: 'text-negative-text',
    }

    return (
        <div className="flex items-center gap-3">
            <p className={`text-sm ${tono[state]}`} role="status" aria-live="polite">
                {etiqueta[state]}

                {state === 'synced' && lastSavedAt && (
                    <span className="hint ms-1">
                        {new Date(lastSavedAt).toLocaleTimeString()}
                    </span>
                )}
            </p>

            {/* "Guardar ahora" sigue existiendo aunque el guardado sea
                automatico: quien acaba de escribir algo importante quiere
                poder confirmarlo sin esperar. */}
            <button
                type="button"
                className="btn btn-ghost"
                onClick={onSaveNow}
                disabled={state === 'saving' || state === 'conflict'}
            >
                {t('interface.builder.save_now')}
            </button>
        </div>
    )
}
