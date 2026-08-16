import { Head, Link, router } from '@inertiajs/react'

import DataTable, { type Column } from '@/Components/DataTable'
import EmptyState from '@/Components/EmptyState'
import Pagination, { type Paginated } from '@/Components/Pagination'
import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Deployment {
    ulid: string
    survey_name: string
    version_number: number
    channel: string
    scope: string
    scope_name: string | null
    status: string
    is_applying: boolean
    not_applying_reason: string | null
    starts_at: string | null
    ends_at: string | null
    activate_url: string
    suspend_url: string
    close_url: string
    qr_url: string | null
}

interface Props {
    deployments: Paginated<Deployment>
    indexUrl: string
    surveysUrl: string
}

export default function Index({ deployments, surveysUrl }: Props) {
    const t = useTranslate()

    const columns: Column<Deployment>[] = [
        {
            key: 'survey',
            header: t('interface.deployments.survey'),
            cell: (d) => (
                <div>
                    <span className="block">{d.survey_name}</span>
                    <span className="hint">
                        {t('interface.deployments.version', { number: d.version_number })}
                    </span>
                </div>
            ),
        },
        {
            key: 'channel',
            header: t('interface.deployments.channel'),
            cell: (d) => t(`interface.deployments.channel_${d.channel}`),
        },
        {
            key: 'scope',
            header: t('interface.deployments.scope'),
            cell: (d) =>
                d.scope_name ?? t(`interface.deployments.scope_${d.scope}`),
        },
        {
            key: 'validity',
            header: t('interface.deployments.validity'),
            cell: (d) => {
                // Sin fechas es indefinido, y eso se dice con palabras: un
                // guion no distingue "siempre" de "no se sabe".
                if (d.starts_at === null && d.ends_at === null) {
                    return <span className="hint">{t('interface.deployments.always')}</span>
                }

                return `${d.starts_at ?? '…'} — ${d.ends_at ?? '…'}`
            },
        },
        {
            key: 'status',
            header: t('interface.deployments.status'),
            cell: (d) => (
                <div>
                    {/*
                        "Aplicando" no es lo mismo que "activo": uno activo con
                        inicio manana todavia no recibe respuestas. Se muestran
                        los dos, y el motivo cuando no aplica.
                    */}
                    <span className={`badge ${d.is_applying ? 'badge-active' : 'badge-archived'}`}>
                        {d.is_applying
                            ? t('interface.deployments.applying')
                            : t(`interface.deployments.state_${d.status}`)}
                    </span>

                    {!d.is_applying && d.not_applying_reason !== null && (
                        <span className="hint block">
                            {t(`interface.deployments.reason_${d.not_applying_reason}`)}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            header: t('interface.deployments.actions'),
            cell: (d) => (
                <div className="flex flex-wrap items-center gap-2">
                    {d.qr_url && (
                        <Link href={d.qr_url} className="text-primary text-sm">
                            {t('interface.qr.title')}
                        </Link>
                    )}

                    {d.status === 'active' && (
                        <button
                            type="button"
                            className="text-ink-muted text-sm"
                            onClick={() => router.post(d.suspend_url, {}, { preserveScroll: true })}
                        >
                            {t('interface.deployments.suspend')}
                        </button>
                    )}

                    {d.status === 'suspended' && (
                        <button
                            type="button"
                            className="text-primary text-sm"
                            onClick={() => router.post(d.activate_url, {}, { preserveScroll: true })}
                        >
                            {t('interface.deployments.activate')}
                        </button>
                    )}

                    {/* Cerrar es definitivo: no se reabre. Por eso no aparece
                        en los ya cerrados. */}
                    {d.status !== 'closed' && (
                        <button
                            type="button"
                            className="text-negative-text text-sm"
                            onClick={() => router.post(d.close_url, {}, { preserveScroll: true })}
                        >
                            {t('interface.deployments.close')}
                        </button>
                    )}
                </div>
            ),
        },
    ]

    return (
        <AdminShell>
            <Head title={t('interface.deployments.title')} />

            <div className="page-header">
                <h1>{t('interface.deployments.title')}</h1>
                <p className="hint mt-1">{t('interface.deployments.subtitle')}</p>
            </div>

            <StatusMessage />

            {deployments.data.length === 0 ? (
                <EmptyState
                    title={t('interface.deployments.empty_title')}
                    help={t('interface.deployments.empty_help')}
                >
                    {/* Se crea desde una encuesta, no desde aqui: la version
                        publicada es contexto de la ruta. */}
                    <Link href={surveysUrl} className="btn btn-primary">
                        {t('interface.deployments.go_to_surveys')}
                    </Link>
                </EmptyState>
            ) : (
                <>
                    <DataTable
                        caption={t('interface.deployments.caption')}
                        columns={columns}
                        rows={deployments.data}
                        rowKey={(d) => d.ulid}
                    />

                    <Pagination
                        links={deployments.links}
                        from={deployments.from}
                        to={deployments.to}
                        total={deployments.total}
                    />
                </>
            )}
        </AdminShell>
    )
}
