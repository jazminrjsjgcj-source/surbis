import { Head, Link } from '@inertiajs/react'

import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    branchesUrl: string
    peopleUrl: string
    surveysUrl: string
}

/**
 * Panel de organizacion.
 *
 * NO inventa indicadores. El panel de verdad —que ocurre, que requiere
 * atencion, que cambio— es de la Fase 12 y necesita respuestas que todavia no
 * existen. Un panel con cifras de adorno es la misma clase de mentira que un
 * menu con secciones que no llevan a ningun sitio.
 */
export default function Dashboard({ branchesUrl, peopleUrl, surveysUrl }: Props) {
    const t = useTranslate()

    return (
        <AdminShell>
            <Head title={t('interface.dashboard.title')} />

            <div className="page-header">
                <h1>{t('interface.dashboard.title')}</h1>
                <p className="hint mt-1">{t('interface.dashboard.subtitle')}</p>
            </div>

            <div className="card card-pad">
                <h2 className="text-lg">{t('interface.dashboard.available')}</h2>
                <p className="hint mt-1 mb-4">{t('interface.dashboard.available_help')}</p>

                <div className="actions">
                    <Link href={branchesUrl} className="btn btn-primary">
                        {t('interface.nav.branches')}
                    </Link>

                    <Link href={peopleUrl} className="btn btn-ghost">
                        {t('interface.nav.people')}
                    </Link>

                    <Link href={surveysUrl} className="btn btn-ghost">
                        {t('interface.nav.surveys')}
                    </Link>
                </div>
            </div>

            <div className="card card-pad mt-4">
                <h2 className="text-lg">{t('interface.dashboard.pending')}</h2>
                <p className="hint mt-1">{t('interface.dashboard.pending_help')}</p>
            </div>
        </AdminShell>
    )
}
