import type { SaveState } from '@/lib/useDraftSaving'
import { useTranslate } from '@/lib/translate'

interface Props {
    state: SaveState
    dirty: boolean
    rejection: string | null
    lastSavedAt: number | null
    onSave: () => void
}

/**
 * El boton de guardar, flotante.
 *
 * Flotante y no en la cabecera porque el constructor se desplaza: con veinte
 * preguntas, un boton arriba obliga a subir cada vez que se quiere guardar.
 *
 * Y dice si hay cambios sin guardar. Sin eso, la pregunta "¿lo guarde?" solo
 * se resuelve pulsando otra vez.
 */
export default function SaveDraftButton({
    state,
    dirty,
    rejection,
    lastSavedAt,
    onSave,
}: Props) {
    const t = useTranslate()

    return (
        <div className="save-dock">
            {/*
                role="status" para que un lector de pantalla anuncie el
                cambio: quien no ve el boton tambien tiene que enterarse de
                que se guardo.
            */}
            <span className="save-dock-state" role="status">
                {state === 'saving' && t('interface.builder.saving')}
                {state === 'saved' && ! dirty && t('interface.builder.saved')}
                {state === 'rejected' && (rejection ?? t('interface.builder.save_failed'))}
                {dirty && state !== 'saving' && t('interface.builder.unsaved')}
            </span>

            <button
                type="button"
                className="btn btn-primary btn-lg"
                // Sin cambios no hay nada que guardar, y ofrecerlo invitaria
                // a pulsar por si acaso.
                disabled={state === 'saving' || (! dirty && state !== 'rejected')}
                onClick={onSave}
            >
                {t('interface.builder.save_draft')}
            </button>

            {lastSavedAt !== null && ! dirty && (
                <span className="hint">
                    {t('interface.builder.saved_at', {
                        time: new Date(lastSavedAt).toLocaleTimeString(),
                    })}
                </span>
            )}
        </div>
    )
}
