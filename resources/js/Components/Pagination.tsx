import { Link } from '@inertiajs/react'

import { useTranslate } from '@/lib/translate'

export interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

export interface Paginated<T> {
    data: T[]
    links: PaginationLink[]
    from: number | null
    to: number | null
    total: number
}

interface Props {
    links: PaginationLink[]
    from: number | null
    to: number | null
    total: number
}

/**
 * Paginacion.
 *
 * Usa <Link> de Inertia y no <a>: asi la navegacion no recarga la pagina
 * entera, que es media razon por la que existe Inertia.
 *
 * Las etiquetas vienen de Laravel y traen entidades HTML —&laquo; para
 * "anterior"— asi que se limpian aqui en lugar de inyectar HTML sin
 * escapar.
 */
export default function Pagination({ links, from, to, total }: Props) {
    const t = useTranslate()

    // Con una sola pagina no se pinta nada: tres botones inertes ocupan
    // espacio y no informan.
    if (links.length <= 3) {
        return null
    }

    function etiqueta(label: string): string {
        if (label.includes('previous') || label.includes('&laquo;')) {
            return t('interface.pagination.previous')
        }

        if (label.includes('next') || label.includes('&raquo;')) {
            return t('interface.pagination.next')
        }

        return label
    }

    return (
        <nav className="pagination" aria-label={t('interface.pagination.label')}>
            <p>
                {t('interface.pagination.showing_plain', {
                    first: from ?? 0,
                    last: to ?? 0,
                    total,
                })}
            </p>

            <div className="pagination-links">
                {links.map((enlace, indice) =>
                    enlace.url === null ? (
                        <span key={indice} className="pagination-link" aria-disabled="true">
                            {etiqueta(enlace.label)}
                        </span>
                    ) : (
                        <Link
                            key={indice}
                            href={enlace.url}
                            className="pagination-link"
                            aria-current={enlace.active ? 'page' : undefined}
                            preserveScroll
                        >
                            {etiqueta(enlace.label)}
                        </Link>
                    ),
                )}
            </div>
        </nav>
    )
}
