import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'

import AssignPanel from '@/Components/AssignPanel'
import { type BranchOption } from '@/Components/BranchAreaPicker'
import DataTable, { type Column } from '@/Components/DataTable'
import EmptyState from '@/Components/EmptyState'
import ErrorSummary from '@/Components/ErrorSummary'
import FilterBar from '@/Components/FilterBar'
import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Row {
    key: string
    name: string
    email: string | null
    branch: string | null
    area: string | null
    has_account: boolean
    is_evaluated: boolean
    role: string | null
    membership_status: string | null
    staff_status: string | null
    branch_id: number | null
    area_id: number | null
    suspend_url: string | null
    activate_url: string | null
    assign_url: string | null
    edit_url: string | null
    account_url: string | null
}

interface Props {
    rows: Row[]
    filters: { q: string; type: string }
    branches: BranchOption[]
    inviteUrl: string
    personUrl: string
    indexUrl: string
    errors: Record<string, string>
}

export default function Index({
    rows,
    filters,
    branches,
    inviteUrl,
    personUrl,
    indexUrl,
    errors,
}: Props) {
    const t = useTranslate()
    const [asignando, setAsignando] = useState<string | null>(null)
    const filtrando = filters.q !== '' || filters.type !== ''

    /*
     * Que es cada persona, en palabras.
     *
     * D-018: sin guiones ni abreviaturas. "No inicia sesion" dice mas que un
     * guion, y quien lee la tabla no tiene que deducir que significa una
     * celda vacia.
     */
    function tipo(row: Row): string {
        if (row.has_account && row.is_evaluated) {
            return t('interface.people.kind_account_evaluated')
        }

        return row.has_account
            ? t('interface.people.kind_account')
            : t('interface.people.kind_evaluated')
    }

    const columns: Column<Row>[] = [
        {
            key: 'name',
            header: t('interface.people.name'),
            cell: (row) => (
                <div>
                    <span>{row.name}</span>
                    {row.email === null && (
                        <span className="hint block">{t('interface.people.no_login')}</span>
                    )}
                </div>
            ),
        },
        {
            key: 'email',
            header: t('interface.people.email'),
            cell: (row) => row.email ?? <span className="hint">—</span>,
        },
        { key: 'kind', header: t('interface.people.kind'), cell: tipo },
        {
            key: 'role',
            header: t('interface.people.role'),
            cell: (row) =>
                row.role === null ? '' : t(`interface.people.role_${row.role}`),
        },
        {
            key: 'branch',
            header: t('interface.people.branch'),
            cell: (row) => row.branch ?? <span className="hint">{t('interface.people.no_branch')}</span>,
        },
        { key: 'area', header: t('interface.people.area'), cell: (row) => row.area ?? '' },
        {
            key: 'status',
            header: t('interface.people.status'),
            cell: (row) => {
                const estado = row.membership_status ?? row.staff_status
                if (estado === null) return ''

                return (
                    <span className={`badge ${estado === 'active' ? 'badge-active' : 'badge-archived'}`}>
                        {t(`interface.people.state_${estado}`)}
                    </span>
                )
            },
        },
        {
            key: 'actions',
            header: t('interface.people.actions'),
            cell: (row) => (
                <div className="flex flex-wrap items-center gap-2">
                    {row.edit_url && (
                        <Link href={row.edit_url} className="text-primary text-sm">
                            {t('interface.people.edit')}
                        </Link>
                    )}

                    {/* Solo para quien NO tiene cuenta. Lo decide el servidor:
                        si React lo dedujera, su criterio y el de la Policy
                        podrian divergir. */}
                    {row.account_url && (
                        <Link href={row.account_url} className="text-primary text-sm">
                            {t('interface.people.give_account')}
                        </Link>
                    )}

                    {row.assign_url && (
                        <button
                            type="button"
                            className="text-primary text-sm"
                            onClick={() => setAsignando(asignando === row.key ? null : row.key)}
                        >
                            {t('interface.people.assign')}
                        </button>
                    )}

                    {row.membership_status === 'active' && row.suspend_url && (
                        <button
                            type="button"
                            className="text-ink-muted text-sm"
                            onClick={() => router.post(row.suspend_url!, {}, { preserveScroll: true })}
                        >
                            {t('interface.people.suspend')}
                        </button>
                    )}

                    {row.membership_status === 'suspended' && row.activate_url && (
                        <button
                            type="button"
                            className="text-primary text-sm"
                            onClick={() => router.post(row.activate_url!, {}, { preserveScroll: true })}
                        >
                            {t('interface.people.activate')}
                        </button>
                    )}
                </div>
            ),
        },
    ]

    return (
        <AdminShell>
            <Head title={t('interface.people.title')} />

            <div className="page-header">
                <h1>{t('interface.people.title')}</h1>
                <p className="hint mt-1">{t('interface.people.subtitle')}</p>
            </div>

            <StatusMessage />
            <ErrorSummary errors={errors} />

            <div className="toolbar">
                <FilterBar action={indexUrl} initial={filters}>
                    {(values, set) => (
                        <>
                            <div className="field toolbar-grow">
                                <label htmlFor="q">{t('interface.people.search')}</label>
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
                                <label htmlFor="type">{t('interface.people.kind')}</label>
                                <select
                                    id="type"
                                    name="type"
                                    className="input"
                                    value={values.type ?? ''}
                                    onChange={(e) => set('type', e.target.value)}
                                >
                                    <option value="">{t('interface.people.filter_all')}</option>
                                    <option value="accounts">
                                        {t('interface.people.filter_accounts')}
                                    </option>
                                    <option value="evaluated">
                                        {t('interface.people.filter_evaluated')}
                                    </option>
                                </select>
                            </div>
                        </>
                    )}
                </FilterBar>

                <div className="actions ms-auto">
                    <Link href={personUrl} className="btn btn-ghost">
                        {t('interface.people.person_new')}
                    </Link>

                    <Link href={inviteUrl} className="btn btn-primary">
                        {t('interface.people.invite')}
                    </Link>
                </div>
            </div>

            {rows.length === 0 ? (
                filtrando ? (
                    <EmptyState
                        title={t('interface.people.empty_search_title')}
                        help={t('interface.people.empty_search_help')}
                    >
                        <Link href={indexUrl} className="btn btn-ghost">
                            {t('interface.people.clear_filters')}
                        </Link>
                    </EmptyState>
                ) : (
                    <EmptyState
                        title={t('interface.people.empty_title')}
                        help={t('interface.people.empty_help')}
                    >
                        <Link href={inviteUrl} className="btn btn-primary">
                            {t('interface.people.invite')}
                        </Link>
                    </EmptyState>
                )
            ) : (
                <>
                    <DataTable
                        caption={t('interface.people.caption')}
                        columns={columns}
                        rows={rows}
                        rowKey={(row) => row.key}
                    />

                    {/*
                        La asignacion se abre bajo la tabla y no en un dialogo.
                        Con un dialogo habria que resolver foco, escape y
                        lectores de pantalla para una operacion de dos
                        desplegables; asi se resuelve solo.
                    */}
                    {asignando && (
                        <AssignPanel
                            row={rows.find((row) => row.key === asignando)!}
                            branches={branches}
                            onClose={() => setAsignando(null)}
                        />
                    )}
                </>
            )}
        </AdminShell>
    )
}
