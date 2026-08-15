import { Head, Link } from '@inertiajs/react'

import AuthShell from '@/Layouts/AuthShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    module: string
    logoutUrl: string
}

/**
 * Marcador de un modulo que todavia no existe.
 *
 * Existe para que la redireccion por rol de RF-AUT-003 tenga un destino real
 * que probar: una prueba que espera un 404 no prueba lo que dice probar.
 *
 * Cada uno se sustituye por su modulo en su fase. El panel de plataforma
 * llega en la Fase 18 y la preparacion de quiosco en la Fase 8.
 */
export default function Placeholder({ module, logoutUrl }: Props) {
    const t = useTranslate()

    return (
        <AuthShell title={module}>
            <Head title={module} />

            <div className="text-center">
                <h1 className="text-xl">{module}</h1>
                <p className="hint mt-2 mb-4">{t('interface.placeholder.not_built')}</p>

                {/* Cerrar sesion es lo unico que se puede hacer aqui, y tiene
                    que estar: sin ello, quien aterrice en esta pantalla se
                    queda sin salida. */}
                <Link href={logoutUrl} method="post" as="button" className="btn btn-ghost">
                    {t('interface.session.logout')}
                </Link>
            </div>
        </AuthShell>
    )
}
