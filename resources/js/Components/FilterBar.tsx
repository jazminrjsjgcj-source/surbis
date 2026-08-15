import { router } from '@inertiajs/react'
import type { FormEvent, ReactNode } from 'react'
import { useState } from 'react'

import { useTranslate } from '@/lib/translate'

interface Props {
    action: string
    initial: Record<string, string>
    children: (values: Record<string, string>, set: (key: string, value: string) => void) => ReactNode
}

/**
 * Barra de busqueda y filtros.
 *
 * Envia por GET con Inertia en lugar de recargar: `preserveState` false a
 * proposito, porque el resultado ES otro conjunto de datos y conservar el
 * estado anterior mostraria filtros que no corresponden a lo que se ve.
 *
 * Los campos los pone quien la usa: cada pantalla filtra por cosas
 * distintas, y una barra que intentara adivinarlas acabaria con un array de
 * configuracion mas dificil de leer que el propio formulario.
 */
export default function FilterBar({ action, initial, children }: Props) {
    const t = useTranslate()
    const [values, setValues] = useState(initial)

    function set(key: string, value: string): void {
        setValues((actuales) => ({ ...actuales, [key]: value }))
    }

    function submit(event: FormEvent): void {
        event.preventDefault()

        // Las claves vacias no viajan: una URL con ?q=&status= es mas dificil
        // de leer y de compartir que una limpia.
        const limpio = Object.fromEntries(
            Object.entries(values).filter(([, valor]) => valor !== ''),
        )

        router.get(action, limpio, { preserveScroll: true })
    }

    return (
        <form method="GET" action={action} onSubmit={submit} className="toolbar contents">
            {children(values, set)}

            <button type="submit" className="btn btn-ghost">
                {t('interface.filters.apply')}
            </button>
        </form>
    )
}
