import { Link, usePage } from '@inertiajs/react'
import type { ReactNode } from 'react'

import { useTranslate } from '@/lib/translate'

interface NavItem {
    key: string
    url: string
}

interface PageProps {
    nav: NavItem[]
    [key: string]: unknown
}

interface Props {
    children: ReactNode
    logoutUrl?: string
    securityUrl?: string
}

/**
 * Marco de administracion, en React.
 *
 * Replica el de Blade —resources/views/components/layouts/admin.blade.php—
 * porque una pagina de Inertia no puede usarlo. Mientras convivan los dos
 * sistemas habra dos versiones de esto, y es deuda conocida: el de Blade se
 * borra cuando la ultima pantalla pase a React.
 *
 * La navegacion viene del servidor: React no conoce las rutas nombradas de
 * Laravel, y escribirlas aqui crearia una segunda verdad sobre las mismas
 * direcciones.
 */
export default function AdminShell({ children, logoutUrl = '/logout', securityUrl = '/cuenta/seguridad' }: Props) {
    const t = useTranslate()

    /*
     * usePage() devuelve { props, url, component, version }.
     *
     * Las props compartidas viven DENTRO de props, no en la raiz. La primera
     * version desestructuraba `{ nav, url }` directamente y `nav` salia
     * undefined: el servidor las mandaba perfectas —se veian en el JSON— y el
     * componente miraba en el sitio equivocado.
     *
     * El sintoma era una pantalla en blanco con "Cannot read properties of
     * undefined (reading 'map')". Costo cuatro hipotesis descartadas, y
     * ninguna prueba de servidor podia verlo: las props llegaban bien.
     */
    const { props, url } = usePage<PageProps>()
    const nav = props.nav ?? []

    return (
        <div className="shell">
            <nav className="shell-nav" aria-label={t('interface.nav.label')}>
                <p className="brand">
                    <span className="brand-mark" aria-hidden="true">P</span>
                    <span className="font-display font-bold">Pulso</span>
                </p>

                <div className="nav-section">
                    <p className="nav-label">{t('interface.nav.organization')}</p>

                    {nav.map((item) => (
                        <Link
                            key={item.key}
                            href={item.url}
                            className="nav-link"
                            // aria-current en el marcado, no solo un color:
                            // quien no ve la pantalla tambien tiene que saber
                            // donde esta.
                            aria-current={url.startsWith(new URL(item.url, 'http://x').pathname) ? 'page' : undefined}
                        >
                            {t(`interface.nav.${item.key}`)}
                        </Link>
                    ))}
                </div>
            </nav>

            <div className="shell-main">
                <header className="shell-topbar">
                    <Link href={securityUrl} className="text-primary text-sm">
                        {t('interface.nav.security')}
                    </Link>

                    <Link href={logoutUrl} method="post" as="button" className="btn btn-ghost">
                        {t('interface.session.logout')}
                    </Link>
                </header>

                <main className="shell-content">{children}</main>
            </div>
        </div>
    )
}
