import { useEffect, useRef } from 'react'

import { useTranslate } from '@/lib/translate'

interface Props {
    open: boolean
    title: string
    body: string
    confirmLabel: string
    destructive?: boolean
    onConfirm: () => void
    onCancel: () => void
}

/**
 * Confirmacion antes de una accion que destruye algo.
 *
 * Usa <dialog> nativo y showModal(), no un div con position fixed. El
 * navegador resuelve gratis lo que cuesta hacer bien a mano: atrapar el foco
 * dentro, cerrar con Escape, marcar el resto de la pagina como inerte para
 * lectores de pantalla, y devolver el foco al elemento que lo abrio.
 *
 * Escribir eso a mano es de las cosas que se hacen a medias y nadie prueba
 * con teclado. RNF-GEN-006.
 *
 * NO se usa en todas partes a proposito: solo donde se pierde algo que cuesta
 * rehacer. Confirmar cada accion entrena a pulsar "si" sin leer, y entonces
 * deja de proteger justo cuando importa.
 */
export default function ConfirmDialog({
    open,
    title,
    body,
    confirmLabel,
    destructive = false,
    onConfirm,
    onCancel,
}: Props) {
    const t = useTranslate()
    const dialog = useRef<HTMLDialogElement>(null)

    useEffect(() => {
        const elemento = dialog.current

        if (!elemento) {
            return
        }

        if (open && !elemento.open) {
            elemento.showModal()
        }

        if (!open && elemento.open) {
            elemento.close()
        }
    }, [open])

    return (
        <dialog
            ref={dialog}
            className="confirm-dialog"
            // El evento close cubre Escape y el clic fuera: sin esto, cerrar
            // con Escape dejaria el estado creyendo que sigue abierto.
            onClose={onCancel}
        >
            <h2 className="text-lg">{title}</h2>
            <p className="mt-2">{body}</p>

            <div className="actions mt-4">
                {/*
                    Cancelar va PRIMERO y es el que recibe el foco: si alguien
                    pulsa Enter por inercia, no destruye nada.
                */}
                <button type="button" className="btn btn-ghost" autoFocus onClick={onCancel}>
                    {t('interface.confirm.cancel')}
                </button>

                <button
                    type="button"
                    className={`btn ${destructive ? 'btn-ghost btn-danger' : 'btn-primary'}`}
                    onClick={onConfirm}
                >
                    {confirmLabel}
                </button>
            </div>
        </dialog>
    )
}
