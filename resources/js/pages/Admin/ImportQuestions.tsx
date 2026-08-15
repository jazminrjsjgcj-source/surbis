import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Problem {
    key: string
    line: number
    replacements: Record<string, string | number>
}

interface Preview {
    type: string
    text: string
    is_required: boolean
    options: number
}

interface Props {
    survey: { ulid: string; name: string }
    action: string
    previewUrl: string
    builderUrl: string
    types: string[]
}

interface PageProps {
    import_problems?: Problem[]
    import_preview?: Preview[]
    [key: string]: unknown
}

const MAX_TEXT = 20000

export default function ImportQuestions({
    survey,
    action,
    previewUrl,
    builderUrl,
    types,
}: Props) {
    const t = useTranslate()
    const { props } = usePage<PageProps>()

    const problems = props.import_problems ?? []
    const preview = props.import_preview ?? []

    const { data, setData, post, processing, errors } = useForm({
        text: '',
        mode: 'append',
    })

    function comprobar(event: FormEvent): void {
        event.preventDefault()
        post(previewUrl, { preserveScroll: true })
    }

    return (
        <AdminShell>
            <Head title={t('interface.import.title')} />

            <div className="page-header">
                <Link href={builderUrl} className="text-primary text-sm">
                    {survey.name}
                </Link>

                <h1 className="mt-1">{t('interface.import.title')}</h1>
                <p className="hint mt-1">{t('interface.import.subtitle')}</p>
            </div>

            <ErrorSummary errors={errors} />

            <div className="builder">
                <div className="card card-pad">
                    <form onSubmit={comprobar}>
                        <div className="field">
                            <label htmlFor="text">{t('interface.import.label')}</label>
                            <textarea
                                id="text"
                                className="input font-mono"
                                rows={16}
                                value={data.text}
                                maxLength={MAX_TEXT}
                                aria-describedby="import-help"
                                onChange={(e) => setData('text', e.target.value)}
                            />
                        </div>

                        <div className="field">
                            <label htmlFor="mode">{t('interface.import.mode')}</label>
                            <select
                                id="mode"
                                className="input"
                                value={data.mode}
                                onChange={(e) => setData('mode', e.target.value)}
                            >
                                <option value="append">{t('interface.import.mode_append')}</option>
                                <option value="replace">{t('interface.import.mode_replace')}</option>
                            </select>
                        </div>

                        <div className="actions">
                            {/* Comprobar antes de importar. Ver lo que va a
                                entrar evita descubrir despues que el tipo era
                                otro y tener que deshacerlo a mano. */}
                            <button type="submit" className="btn btn-ghost" disabled={processing}>
                                {t('interface.import.check')}
                            </button>

                            <button
                                type="button"
                                className="btn btn-primary"
                                disabled={processing || data.text.trim() === ''}
                                onClick={() =>
                                    router.post(action, { text: data.text, mode: data.mode })
                                }
                            >
                                {t('interface.import.submit')}
                            </button>
                        </div>
                    </form>
                </div>

                <div>
                    {problems.length > 0 && (
                        <div className="alert alert-error mb-4" role="alert">
                            <p className="alert-title">{t('interface.import.problems_title')}</p>

                            <ul className="ps-4">
                                {problems.map((problem, indice) => (
                                    <li key={`${problem.key}-${problem.line}-${indice}`}>
                                        {/* La linea, siempre. Sin ella, "hay
                                            un tipo desconocido" en cuarenta
                                            lineas obliga a revisarlas todas. */}
                                        <strong>
                                            {t('interface.import.at_line', { line: problem.line })}{' '}
                                        </strong>
                                        {t(`interface.import.problem_${problem.key}`, problem.replacements)}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {preview.length > 0 && (
                        <div className="card card-pad mb-4">
                            <h2 className="text-lg">
                                {t('interface.import.preview_title', { count: preview.length })}
                            </h2>

                            <ol className="mt-3">
                                {preview.map((question, indice) => (
                                    <li key={indice} className="border-line border-b py-2 last:border-0">
                                        <span className="block">{question.text}</span>

                                        <span className="hint">
                                            {t(`interface.builder.type_${question.type}`)}
                                            {question.options > 0 &&
                                                ` · ${t('interface.import.options_count', { count: question.options })}`}
                                            {question.is_required &&
                                                ` · ${t('interface.builder.required')}`}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    )}

                    <div className="card card-pad" id="import-help">
                        <h2 className="text-lg">{t('interface.import.help_title')}</h2>

                        <pre className="code-block mt-2 text-sm">{t('interface.import.example')}</pre>

                        <p className="hint mt-3">{t('interface.import.help_types')}</p>
                        <p className="mt-1 text-sm">{types.join(' · ')}</p>

                        <p className="hint mt-3">{t('interface.import.help_scores')}</p>
                    </div>
                </div>
            </div>
        </AdminShell>
    )
}
