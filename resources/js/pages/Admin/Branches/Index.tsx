import { Head, Link, router } from '@inertiajs/react'

import DataTable, { type Column } from '@/Components/DataTable'
import EmptyState from '@/Components/EmptyState'
import ErrorSummary from '@/Components/ErrorSummary'
import FilterBar from '@/Components/FilterBar'
import Pagination, { type Paginated } from '@/Components/Pagination'
import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Branch {
    ulid: string
    name: string
    code: string
    is_active: boolean
    areas_count: number
    memberships_count: number
    edit_url: string
    areas_url: string
    archive_url: string
    activate_url: string
}

interface Props {
    branches: Paginated<Branch>
    filters: { q: string; status: string }
    createUrl: string
    indexUrl: string
    errors: Record<string, string>
}

export default function Index({ branches, filters, createUrl, indexUrl, errors }: Props) {
    const t = useTranslate()
    const filtrando = filters.q !== '' || filters.status !== ''

    const columns: Column<Branch>[] = [
        {
            key: 'name',
            header: t('interface.branches.name'),
            cell: (branch) => branch.name,
        },
        {
            key: 'code',
            header: t('interface.branches.code'),
            numeric: true,
            cell: (branch) => branch.code,
        },
        {
            key: 'status',
            header: t('interface.branches.status'),
            // El estado se nombra en texto dentro de la etiqueta: el color
            // acompana, no informa. ANEXO 1 seccion 47.
            cell: (branch) => (
                <span className={`badge ${branch.is_active ? 'badge-active' : 'badge-archived'}`}>
                    {branch.is_active
                        ? t('interface.branches.state_active')
                        : t('interface.branches.state_archived')}
                </span>
            ),
        },
        {
            key: 'areas',
            header: t('interface.branches.areas'),
            numeric: true,
            // El conteo enlaza en lugar de quedarse en un numero que no lleva
            // a ningun sitio.
            cell: (branch) => (
                <Link href={branch.areas_url} className="text-primary">
                    {branch.areas_count}
                </Link>
            ),
        },
        {
            key: 'people',
            header: t('interface.branches.people'),
            numeric: true,
            cell: (branch) => branch.memberships_count,
        },
        {
            key: 'actions',
            header: t('interface.branches.actions'),
            cell: (branch) => (
                <div className="flex flex-wrap items-center gap-2">
                    <Link href={branch.edit_url} className="text-primary text-sm">
                        {t('interface.branches.edit')}
                    </Link>

                    {branch.is_active ? (
                        <button
                            type="button"
                            className="text-ink-muted text-sm"
                            onClick={() => router.post(branch.archive_url, {}, { preserveScroll: true })}
                        >
                            {t('interface.branches.archive')}
                        </button>
                    ) : (
                        <button
                            type="button"
                            className="text-primary text-sm"
                            onClick={() => router.post(branch.activate_url, {}, { preserveScroll: true })}
                        >
                            {t('interface.branches.activate')}
                        </button>
                    )}
                </div>
            ),
        },
    ]

    return (
        <AdminShell>
            <Head title={t('interface.branches.title')} />

            <div className="page-header">
                <h1>{t('interface.branches.title')}</h1>
                <p className="hint mt-1">{t('interface.branches.subtitle')}</p>
            </div>

            <StatusMessage />
            <ErrorSummary errors={errors} />

            <div className="toolbar">
                <FilterBar action={indexUrl} initial={filters}>
                    {(values, set) => (
                        <>
                            <div className="field toolbar-grow">
                                <label htmlFor="q">{t('interface.branches.search')}</label>
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
                                <label htmlFor="status">{t('interface.branches.status')}</label>
                                <select
                                    id="status"
                                    name="status"
                                    className="input"
                                    value={values.status ?? ''}
                                    onChange={(e) => set('status', e.target.value)}
                                >
                                    <option value="">{t('interface.branches.filter_all')}</option>
                                    <option value="active">
                                        {t('interface.branches.filter_active')}
                                    </option>
                                    <option value="archived">
                                        {t('interface.branches.filter_archived')}
                                    </option>
                                </select>
                            </div>
                        </>
                    )}
                </FilterBar>

                <Link href={createUrl} className="btn btn-primary ms-auto">
                    {t('interface.branches.new')}
                </Link>
            </div>

            {branches.data.length === 0 ? (
                /*
                 * Dos vacios distintos. "No hay sucursales" explica que son y
                 * como crear la primera; "ninguna coincide" dice otra cosa y
                 * ofrece otra salida. Un mensaje unico para los dos casos deja
                 * al usuario creyendo que perdio sus datos.
                 */
                filtrando ? (
                    <EmptyState
                        title={t('interface.branches.empty_search_title')}
                        help={t('interface.branches.empty_search_help')}
                    >
                        <Link href={indexUrl} className="btn btn-ghost">
                            {t('interface.branches.clear_filters')}
                        </Link>
                    </EmptyState>
                ) : (
                    <EmptyState
                        title={t('interface.branches.empty_title')}
                        help={t('interface.branches.empty_help')}
                    >
                        <Link href={createUrl} className="btn btn-primary">
                            {t('interface.branches.new')}
                        </Link>
                    </EmptyState>
                )
            ) : (
                <>
                    <DataTable
                        caption={t('interface.branches.caption')}
                        columns={columns}
                        rows={branches.data}
                        rowKey={(branch) => branch.ulid}
                    />

                    <Pagination
                        links={branches.links}
                        from={branches.from}
                        to={branches.to}
                        total={branches.total}
                    />
                </>
            )}
        </AdminShell>
    )
}
