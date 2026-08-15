import type { ReactNode } from 'react'

interface Props {
    title: string
    help?: string
    children?: ReactNode
}

/**
 * Estado vacio.
 *
 * Recibe titulo y ayuda porque NO hay un solo vacio: "todavia no hay nada" y
 * "nada coincide con tu busqueda" son situaciones distintas, con causas
 * distintas y salidas distintas. Un mensaje unico para las dos deja al
 * usuario creyendo que perdio sus datos.
 */
export default function EmptyState({ title, help, children }: Props) {
    return (
        <div className="card card-pad">
            <div className="empty">
                <h2>{title}</h2>
                {help && <p>{help}</p>}
                {children}
            </div>
        </div>
    )
}
