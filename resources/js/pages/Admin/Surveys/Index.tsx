import { Head, Link, router } from '@inertiajs/react'

import DataTable, { type Column } from '@/Components/DataTable'
import EmptyState from '@/Components/EmptyState'
import FilterBar from '@/Components/FilterBar'
import Pagination, { type Paginated } from '@/Components/Pagination'
import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Survey {
    ulid: string
    name: string
    description: string | null
    status: string
    published_version: number | null
    draft_version: number | null
    updated_at: string | null
    edit_url: string
    archive_url: string
    activate_url: string
}

interface Props {
    surveys: Paginated<Survey>
    filters: { q: string; status: string }
    createUrl: string
    indexUrl: string
}

export default function Index({ surveys, filters, createUrl, indexUrl }: Props) {
    const t = useTranslate()
    const filtrando = filters.q !== '' || filters.status !== ''

    const columns: Column<Survey>[] = [
        {
            key: 'name',
            header: t('interface.surveys.name'),
            cell: (survey) => (
                <Link href={survey.edit_url} className="text-primary">
                    {survey.name}
                </Link>
            ),
        },
        {
            key: 'status',
            header: t('interface.surveys.status'),
            cell: (survey) => (
                <span className={`badge ${survey.status === 'archived' ? 'badge-archived' : 'badge-active'}`}>
                    {t(`interface.surveys.state_${survey.status}`)}
                </span>
            ),
        },
        {
            key: 'published',
            header: t('interface.surveys.published_version'),
            numeric: true,
            // Sin version publicada se dice con palabras, no con un guion.
            // Un guion obliga a adivinar si es "ninguna" o "no se sabe".
            cell: (survey) =>
                survey.published_version === null
                    ? <span className="hint">{t('interface.surveys.unpublished')}</span>
                    : survey.published_version,
        },
        {
            key: 'draft',
            header: t('interface.surveys.draft_version'),
            numeric: true,
            cell: (survey) =>
                survey.draft_version === null
                    ? <span className="hint">{t('interface.surveys.no_draft')}</span>
                    : survey.draft_version,
        },
        {
            key: 'updated',
            header: t('interface.surveys.updated'),
            cell: (survey) => survey.updated_at ?? '',
        },
        {
            key: 'actions',
            header: t('interface.surveys.actions'),
            cell: (survey) => (
                <div className="flex flex-wrap items-center gap-2">
                    <Link href={survey.edit_url} className="text-primary text-sm">
                        {t('interface.surveys.open')}
                    </Link>

                    {survey.status === 'archived' ? (
                        <button
                            type="button"
                            className="text-primary text-sm"
                            onClick={() => router.post(survey.activate_url, {}, { preserveScroll: true })}
                        >
                            {t('interface.surveys.activate')}
                        </button>
                    ) : (
                        <button
                            type="button"
                            className="text-ink-muted text-sm"
                            onClick={() => router.post(survey.archive_url, {}, { preserveScroll: true })}
                        >
                            {t('interface.surveys.archive')}
                        </button>
                    )}
                </div>
            ),
        },
    ]

    return (
        <AdminShell>
            <Head title={t('interface.surveys.title')} />

            <div className="page-header">
                <h1>{t('interface.surveys.title')}</h1>
                <p className="hint mt-1">{t('interface.surveys.subtitle')}</p>
            </div>

            <StatusMessage />

            <div className="toolbar">
                <FilterBar action={indexUrl} initial={filters}>
                    {(values, set) => (
                        <>
                            <div className="field toolbar-grow">
                                <label htmlFor="q">{t('interface.surveys.search')}</label>
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
                                <label htmlFor="status">{t('interface.surveys.status')}</label>
                                <select
                                    id="status"
                                    name="status"
                                    className="input"
                                    value={values.status ?? ''}
                                    onChange={(e) => set('status', e.target.value)}
                                >
                                    <option value="">{t('interface.surveys.filter_all')}</option>
                                    <option value="draft">{t('interface.surveys.state_draft')}</option>
                                    <option value="published">{t('interface.surveys.state_published')}</option>
                                    <option value="archived">{t('interface.surveys.state_archived')}</option>
                                </select>
                            </div>
                        </>
                    )}
                </FilterBar>

                <Link href={createUrl} className="btn btn-primary ms-auto">
                    {t('interface.surveys.new')}
                </Link>
            </div>

            {surveys.data.length === 0 ? (
                filtrando ? (
                    <EmptyState
                        title={t('interface.surveys.empty_search_title')}
                        help={t('interface.surveys.empty_search_help')}
                    >
                        <Link href={indexUrl} className="btn btn-ghost">
                            {t('interface.surveys.clear_filters')}
                        </Link>
                    </EmptyState>
                ) : (
                    <EmptyState
                        title={t('interface.surveys.empty_title')}
                        help={t('interface.surveys.empty_help')}
                    >
                        <Link href={createUrl} className="btn btn-primary">
                            {t('interface.surveys.new')}
                        </Link>
                    </EmptyState>
                )
            ) : (
                <>
                    <DataTable
                        caption={t('interface.surveys.caption')}
                        columns={columns}
                        rows={surveys.data}
                        rowKey={(survey) => survey.ulid}
                    />

                    <Pagination
                        links={surveys.links}
                        from={surveys.from}
                        to={surveys.to}
                        total={surveys.total}
                    />
                </>
            )}
        </AdminShell>
    )
}
