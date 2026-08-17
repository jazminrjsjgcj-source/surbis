import { Head, Link } from '@inertiajs/react'
import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts'

import DataTable, { type Column } from '@/Components/DataTable'
import FilterBar from '@/Components/FilterBar'
import MetricCard, { type Metric } from '@/Components/MetricCard'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Group extends Metric {
    group: number | string | null
    name?: string | null
}

interface DayPoint extends Metric {
    day: string
}

interface Props {
    filters: Record<string, string | null>
    summary: Metric
    daily: DayPoint[]
    byBranch: Group[]
    byArea: Group[]
    byStaff: Group[]
    byChannel: Group[]
    updatedAt: string | null
    threshold: number
    branches: { ulid: string; name: string }[]
    channels: string[]
    indexUrl: string
    exportUrl: string
}

export default function Index({
    filters,
    summary,
    daily,
    byBranch,
    byArea,
    byStaff,
    byChannel,
    updatedAt,
    threshold,
    branches,
    channels,
    indexUrl,
    exportUrl,
}: Props) {
    const t = useTranslate()

    /*
     * Solo los días CON datos suficientes entran en el gráfico.
     *
     * Un punto oculto y uno con valor cero se ven igual en una línea, y eso
     * haría leer una caída donde solo hay pocos datos. Se quitan y se dice
     * cuántos faltan.
     */
    const puntos = daily
        .filter((d) => d.available)
        .map((d) => ({ day: d.day, average: d.average, percentage: d.percentage }))

    const ocultos = daily.length - puntos.length

    const columnas = (etiqueta: string): Column<Group>[] => [
        {
            key: 'name',
            header: etiqueta,
            cell: (g) => g.name ?? t('interface.analytics.unassigned'),
        },
        {
            key: 'responses',
            header: t('interface.analytics.responses'),
            numeric: true,
            cell: (g) =>
                g.available ? (
                    String(g.responses)
                ) : (
                    <span className="hint">{t('interface.analytics.insufficient')}</span>
                ),
        },
        {
            key: 'average',
            header: t('interface.analytics.average'),
            numeric: true,
            cell: (g) => (g.available ? (g.average?.toFixed(2) ?? '—') : '—'),
        },
        {
            key: 'percentage',
            header: t('interface.analytics.percentage'),
            numeric: true,
            cell: (g) =>
                g.available && g.percentage !== null ? `${g.percentage}%` : '—',
        },
    ]

    return (
        <AdminShell>
            <Head title={t('interface.analytics.title')} />

            <div className="page-header">
                <h1>{t('interface.analytics.title')}</h1>

                <p className="hint mt-1">
                    {updatedAt === null
                        ? t('interface.analytics.never_updated')
                        : t('interface.analytics.updated_at', {
                              time: new Date(updatedAt).toLocaleString(),
                          })}
                </p>
            </div>

            <div className="toolbar">
                <FilterBar action={indexUrl} initial={filters}>
                    {(values, set) => (
                        <>
                            <div className="field">
                                <label htmlFor="from">{t('interface.analytics.from')}</label>
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
                                <label htmlFor="to">{t('interface.analytics.to')}</label>
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
                                <label htmlFor="branch">{t('interface.analytics.branch')}</label>
                                <select
                                    id="branch"
                                    name="branch"
                                    className="input"
                                    value={values.branch ?? ''}
                                    onChange={(e) => set('branch', e.target.value)}
                                >
                                    <option value="">{t('interface.analytics.all')}</option>
                                    {branches.map((b) => (
                                        <option key={b.ulid} value={b.ulid}>
                                            {b.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="field">
                                <label htmlFor="channel">{t('interface.analytics.channel')}</label>
                                <select
                                    id="channel"
                                    name="channel"
                                    className="input"
                                    value={values.channel ?? ''}
                                    onChange={(e) => set('channel', e.target.value)}
                                >
                                    <option value="">{t('interface.analytics.all')}</option>
                                    {channels.map((c) => (
                                        <option key={c} value={c}>
                                            {t(`interface.deployments.channel_${c}`)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </>
                    )}
                </FilterBar>

                {/* Descarga directa con <a>, no con router: es un archivo,
                    no una navegación de Inertia. */}
                <a href={exportUrl} className="btn btn-ghost">
                    {t('interface.export.download')}
                </a>
            </div>

            <div className="metric-grid">
                <MetricCard
                    label={t('interface.analytics.responses')}
                    metric={summary}
                    kind="responses"
                />
                <MetricCard
                    label={t('interface.analytics.average')}
                    metric={summary}
                    kind="average"
                />
                <MetricCard
                    label={t('interface.analytics.percentage')}
                    metric={summary}
                    kind="percentage"
                />
            </div>

            {puntos.length > 0 && (
                <div className="card card-pad mt-6">
                    <h2 className="text-lg">{t('interface.analytics.over_time')}</h2>

                    {ocultos > 0 && (
                        <p className="hint mt-1">
                            {t('interface.analytics.days_hidden', {
                                count: ocultos,
                                threshold,
                            })}
                        </p>
                    )}

                    <div className="mt-3 h-64">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={puntos}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="day" />
                                <YAxis />
                                <Tooltip />
                                {/*
                                    Sin `dot` en series largas y con
                                    connectNulls desactivado: unir dos puntos
                                    separados por días ocultos dibujaría una
                                    tendencia que no existe.
                                */}
                                <Line
                                    type="monotone"
                                    dataKey="percentage"
                                    stroke="var(--color-primary)"
                                    connectNulls={false}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            )}

            {[
                { datos: byBranch, etiqueta: t('interface.analytics.branch') },
                { datos: byArea, etiqueta: t('interface.analytics.area') },
                { datos: byStaff, etiqueta: t('interface.analytics.staff') },
            ].map(({ datos, etiqueta }) =>
                datos.length === 0 ? null : (
                    <div key={etiqueta} className="mt-6">
                        <h2 className="text-lg">{etiqueta}</h2>

                        <DataTable
                            caption={etiqueta}
                            columns={columnas(etiqueta)}
                            rows={datos}
                            rowKey={(g) => String(g.group)}
                        />
                    </div>
                ),
            )}

            {byChannel.length > 0 && (
                <div className="mt-6">
                    <h2 className="text-lg">{t('interface.analytics.channel')}</h2>

                    <DataTable
                        caption={t('interface.analytics.channel')}
                        columns={columnas(t('interface.analytics.channel'))}
                        rows={byChannel.map((g) => ({
                            ...g,
                            name: t(`interface.deployments.channel_${g.group}`),
                        }))}
                        rowKey={(g) => String(g.group)}
                    />
                </div>
            )}
        </AdminShell>
    )
}
