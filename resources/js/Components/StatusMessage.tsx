import { usePage } from '@inertiajs/react'

interface PageProps {
    flash: { status: string | null }
    [key: string]: unknown
}

/**
 * Mensaje de exito de una accion anterior.
 *
 * role="status" y no "alert": se anuncia sin interrumpir lo que el lector de
 * pantalla este leyendo, porque no es un error.
 */
export default function StatusMessage() {
    const { flash } = usePage<PageProps>().props

    if (!flash?.status) {
        return null
    }

    return (
        <div className="alert alert-ok mb-4" role="status">
            {flash.status}
        </div>
    )
}
