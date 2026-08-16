import { Head, Link } from '@inertiajs/react'

import DataTable, { type Column } from '@/Components/DataTable'
import EmptyState from '@/Components/EmptyState'
import FilterBar from '@/Components/FilterBar'
import Pagination, { type Paginated } from '@/Components/Pagination'
import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Row {
    ulid: string
    submittedAt: string | null
    surveyName: string
    versionNumber: number
    branchName: string | null
    areaName: string | null
    channel: string
    score: number | null
    maxScore: number | null
    isInvalidated: boolean
    url: string
}

interface Props {
    responses: Paginated<Row> | null
    total: number
    thresholdMet: boolean
    threshold: number
    filters: Record<string, string | null>
    surveys: { ulid: string; name: string }[]
    branches: { ulid: string; name: string }[]
    channels: string[]
    indexUrl: string
}

export default function Index({
    responses,
    total,
    thresholdMet,
    threshold,
    filters,
    surveys,
    branches,
    channels,
    indexUrl,
}: Props) {
    const t = useTranslate()

    const columns: Column<Row>[] = [
        {
            key: 'date',
            header: t('interface.responses.date'),
            cell: (r) => (
                <Link href={r.url} className="text-primary">
                    {r.submittedAt}
                </Link>
            ),
        },
        {
            key: 'survey',
            header: t('interface.responses.survey'),
            cell: (r) => (
                <div>
                    <span className="block">{r.surveyName}</span>
                    <span className="hint">
                        {t('interface.responses.version', { number: r.versionNumber })}
                    </span>
                </div>
            ),
        },
        {
            key: 'where',
            header: t('interface.responses.where'),
            cell: (r) =>
                r.branchName === null ? (
                    <span className="hint">{t('interface.responses.no_branch')}</span>
                ) : (
                    <div>
                        <span className="block">{r.branchName}</span>
                        {r.areaName && <span className="hint">{r.areaName}</span>}
                    </div>
                ),
        },
        {
            key: 'channel',
            header: t('interface.responses.channel'),
            cell: (r) => t(`interface.deployments.channel_${r.channel}`),
        },
        {
            key: 'score',
            header: t('interface.responses.score'),
            numeric: true,
            cell: (r) =>
                r.score === null ? (
                    // Sin puntuación se dice con palabras: un guion no
                    // distingue "no puntúa" de "no se sabe".
                    <span className="hint">{t('interface.responses.no_score')}</span>
                ) : (
                    `${r.score} / ${r.maxScore}`
                ),
        },
        {
            key: 'validity',
            header: t('interface.responses.validity'),
            cell: (r) =>
                r.isInvalidated ? (
                    <span className="badge badge-archived">
                        {t('interface.responses.invalid')}
                    </span>
                ) : (
                    <span className="badge badge-active">{t('interface.responses.valid')}</span>
                ),
        },
    ]

    return (
        <AdminShell>
            <Head title={t('interface.responses.title')} />

            <div className="page-header">
                <h1>{t('interface.responses.title')}</h1>
                <p className="hint mt-1">{t('interface.responses.subtitle')}</p>
            </div>

            <StatusMessage />

            <div className="toolbar">
                <FilterBar action={indexUrl} initial={filters}>
                    {(values, set) => (
                        <>
                            <div className="field">
                                <label htmlFor="from">{t('interface.responses.from')}</label>
                                <input
                                    id="from"
                                    name="from"
                                    type="date"
                                    className="input"
                                    value={values.from ?? ''}
                                    onChange={(e) => set('from', e.target.value)}
                                />
                            </div>

                            <div className="field">
                                <label htmlFor="to">{t('interface.responses.to')}</label>
                                <input
                                    id="to"
                                    name="to"
                                    type="date"
                                    className="input"
                                    value={values.to ?? ''}
                                    onChange={(e) => set('to', e.target.value)}
                                />
                            </div>

                            <div className="field">
                                <label htmlFor="survey">{t('interface.responses.survey')}</label>
                                <select
                                    id="survey"
                                    name="survey"
                                    className="input"
                                    value={values.survey ?? ''}
                                    onChange={(e) => set('survey', e.target.value)}
                                >
                                    <option value="">{t('interface.responses.all')}</option>
                                    {surveys.map((s) => (
                                        <option key={s.ulid} value={s.ulid}>
                                            {s.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="field">
                                <label htmlFor="branch">{t('interface.responses.branch')}</label>
                                <select
                                    id="branch"
                                    name="branch"
                                    className="input"
                                    value={values.branch ?? ''}
                                    onChange={(e) => set('branch', e.target.value)}
                                >
                                    <option value="">{t('interface.responses.all')}</option>
                                    {branches.map((b) => (
                                        <option key={b.ulid} value={b.ulid}>
                                            {b.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="field">
                                <label htmlFor="channel">{t('interface.responses.channel')}</label>
                                <select
                                    id="channel"
                                    name="channel"
                                    className="input"
                                    value={values.channel ?? ''}
                                    onChange={(e) => set('channel', e.target.value)}
                                >
                                    <option value="">{t('interface.responses.all')}</option>
                                    {channels.map((c) => (
                                        <option key={c} value={c}>
                                            {t(`interface.deployments.channel_${c}`)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="field">
                                <label htmlFor="validity">
                                    {t('interface.responses.validity')}
                                </label>
                                <select
                                    id="validity"
                                    name="validity"
                                    className="input"
                                    value={values.validity ?? ''}
                                    onChange={(e) => set('validity', e.target.value)}
                                >
                                    <option value="">{t('interface.responses.all')}</option>
                                    <option value="valid">{t('interface.responses.valid')}</option>
                                    <option value="invalid">
                                        {t('interface.responses.invalid')}
                                    </option>
                                </select>
                            </div>
                        </>
                    )}
                </FilterBar>
            </div>

            {/*
                Por debajo del umbral no se muestran las filas, y se explica
                POR QUE. Un listado vacío sin explicación parece un fallo, y
                quien lo vea repetirá el filtro pensando que se equivocó.
            */}
            {!thresholdMet ? (
                <EmptyState
                    title={t('interface.responses.threshold_title')}
                    help={t('interface.responses.threshold_help', {
                        total,
                        threshold,
                    })}
                >
                    <Link href={indexUrl} className="btn btn-ghost">
                        {t('interface.responses.clear_filters')}
                    </Link>
                </EmptyState>
            ) : responses === null || responses.data.length === 0 ? (
                <EmptyState
                    title={t('interface.responses.empty_title')}
                    help={t('interface.responses.empty_help')}
                />
            ) : (
                <>
                    <DataTable
                        caption={t('interface.responses.caption')}
                        columns={columns}
                        rows={responses.data}
                        rowKey={(r) => r.ulid}
                    />

                    <Pagination
                        links={responses.links}
                        from={responses.from}
                        to={responses.to}
                        total={responses.total}
                    />
                </>
            )}
        </AdminShell>
    )
}
