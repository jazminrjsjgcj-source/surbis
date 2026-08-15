import { Head } from '@inertiajs/react'
import type { ReactNode } from 'react'

interface Props {
    title: string
    subtitle?: string
    children: ReactNode
}

/**
 * Marco de las pantallas sin sesion.
 *
 * El degradado vino con la textura de olas viene de la identidad municipal de
 * La Paz. Las clases son las mismas que en Blade: el sistema de diseno vive
 * en app.css y no se ha tocado al cambiar de capa de presentacion.
 */
export default function AuthShell({ title, subtitle, children }: Props) {
    return (
        <>
            <Head title={title} />

            <div className="auth-backdrop grid min-h-screen place-items-center p-6">
                <div className="w-full max-w-100">
                    <header className="mb-5 text-center">
                        <p className="font-display text-3xl leading-none font-extrabold tracking-tight text-white">
                            PULSO{' '}
                            <span className="font-script text-wordmark-accent text-2xl">Sí</span>
                        </p>

                        {subtitle && (
                            <p className="mt-1.5 text-xs font-semibold tracking-widest text-white/90 uppercase">
                                {subtitle}
                            </p>
                        )}
                    </header>

                    <div className="card card-pad">{children}</div>
                </div>
            </div>
        </>
    )
}
