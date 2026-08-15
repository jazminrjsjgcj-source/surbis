import { Head, Link, router } from '@inertiajs/react'

import DataTable, { type Column } from '@/Components/DataTable'
import EmptyState from '@/Components/EmptyState'
import ErrorSummary from '@/Components/ErrorSummary'
import FilterBar from '@/Components/FilterBar'
import Pagination, { type Paginated } from '@/Components/Pagination'
import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Area {
    ulid: string
    name: string
    code: string
    is_active: boolean
    memberships_count: number
    staff_members_count: number
    edit_url: string
    archive_url: string
    activate_url: string
}

interface Props {
    branch: { ulid: string; name: string; is_active: boolean }
    areas: Paginated<Area>
    filters: { q: string; status: string }
    createUrl: string
    indexUrl: string
    branchesUrl: string
    errors: Record<string, string>
}

export default function Index({
    branch,
    areas,
    filters,
    createUrl,
    indexUrl,
    branchesUrl,
    errors,
}: Props) {
    const t = useTranslate()
    const filtrando = filters.q !== '' || filters.status !== ''

    const columns: Column<Area>[] = [
        { key: 'name', header: t('interface.areas.name'), cell: (area) => area.name },
        { key: 'code', header: t('interface.areas.code'), numeric: true, cell: (area) => area.code },
        {
            key: 'status',
            header: t('interface.areas.status'),
            cell: (area) => (
                <span className={`badge ${area.is_active ? 'badge-active' : 'badge-archived'}`}>
                    {area.is_active
                        ? t('interface.areas.state_active')
                        : t('interface.areas.state_archived')}
                </span>
            ),
        },
        {
            key: 'people',
            header: t('interface.areas.people'),
            numeric: true,
            cell: (area) => area.memberships_count,
        },
        {
            key: 'evaluable',
            header: t('interface.areas.evaluable'),
            numeric: true,
            cell: (area) => area.staff_members_count,
        },
        {
            key: 'actions',
            header: t('interface.areas.actions'),
            cell: (area) => (
                <div className="flex flex-wrap items-center gap-2">
                    <Link href={area.edit_url} className="text-primary text-sm">
                        {t('interface.areas.edit')}
                    </Link>

                    {area.is_active ? (
                        <button
                            type="button"
                            className="text-ink-muted text-sm"
                            onClick={() => router.post(area.archive_url, {}, { preserveScroll: true })}
                        >
                            {t('interface.areas.archive')}
                        </button>
                    ) : (
                        <button
                            type="button"
                            className="text-primary text-sm"
                            onClick={() => router.post(area.activate_url, {}, { preserveScroll: true })}
                        >
                            {t('interface.areas.activate')}
                        </button>
                    )}
                </div>
            ),
        },
    ]

    return (
        <AdminShell>
            <Head title={t('interface.areas.title', { branch: branch.name })} />

            <div className="page-header">
                {/* La ruta de vuelta, porque esta pantalla cuelga de una
                    sucursal y sin ella no hay forma evidente de subir. */}
                <Link href={branchesUrl} className="text-primary text-sm">
                    {t('interface.areas.back')}
                </Link>

                <h1 className="mt-1">{t('interface.areas.title', { branch: branch.name })}</h1>
                <p className="hint mt-1">{t('interface.areas.subtitle')}</p>
            </div>

            <StatusMessage />
            <ErrorSummary errors={errors} />

            {/* Una sucursal archivada no admite areas activas (D-017).
                Decirlo aqui evita intentar activar una y no entender el
                rechazo. */}
            {!branch.is_active && (
                <div className="alert alert-neutral mb-4" role="status">
                    {t('interface.areas.branch_archived')}
                </div>
            )}

            <div className="toolbar">
                <FilterBar action={indexUrl} initial={filters}>
                    {(values, set) => (
                        <>
                            <div className="field toolbar-grow">
                                <label htmlFor="q">{t('interface.areas.search')}</label>
                                <input
                                    id="q"
                                    name="q"
                                    type="search"
                                    className="input"
                                    value={values.q ?? ''}
                                    onChange={(e) => set('q', e.target.value)}
                                />
                            </div>

                            <div className="field">
                                <label htmlFor="status">{t('interface.areas.status')}</label>
                                <select
                                    id="status"
                                    name="status"
                                    className="input"
                                    value={values.status ?? ''}
                                    onChange={(e) => set('status', e.target.value)}
                                >
                                    <option value="">{t('interface.areas.filter_all')}</option>
                                    <option value="active">{t('interface.areas.filter_active')}</option>
                                    <option value="archived">
                                        {t('interface.areas.filter_archived')}
                                    </option>
                                </select>
                            </div>
                        </>
                    )}
                </FilterBar>

                <Link href={createUrl} className="btn btn-primary ms-auto">
                    {t('interface.areas.new')}
                </Link>
            </div>

            {areas.data.length === 0 ? (
                filtrando ? (
                    <EmptyState
                        title={t('interface.areas.empty_search_title')}
                        help={t('interface.areas.empty_search_help')}
                    >
                        <Link href={indexUrl} className="btn btn-ghost">
                            {t('interface.areas.clear_filters')}
                        </Link>
                    </EmptyState>
                ) : (
                    <EmptyState
                        title={t('interface.areas.empty_title')}
                        help={t('interface.areas.empty_help')}
                    >
                        <Link href={createUrl} className="btn btn-primary">
                            {t('interface.areas.new')}
                        </Link>
                    </EmptyState>
                )
            ) : (
                <>
                    <DataTable
                        caption={t('interface.areas.caption')}
                        columns={columns}
                        rows={areas.data}
                        rowKey={(area) => area.ulid}
                    />

                    <Pagination
                        links={areas.links}
                        from={areas.from}
                        to={areas.to}
                        total={areas.total}
                    />
                </>
            )}
        </AdminShell>
    )
}
