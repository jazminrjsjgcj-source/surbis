import { Head, Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Settings {
    version_number: number
    identity_mode: string
    comment_mode: string
    allow_back: boolean
    inactivity_seconds: number
    help_enabled: boolean
    introduction: string | null
    thank_you: string | null
}

interface Props {
    survey: { ulid: string; name: string }
    settings: Settings
    identityModes: string[]
    commentModes: string[]
    action: string
    backUrl: string
    publishedVersion: number | null
}

export default function SettingsPage({
    survey,
    settings,
    identityModes,
    commentModes,
    action,
    backUrl,
    publishedVersion,
}: Props) {
    const t = useTranslate()

    const { data, setData, put, processing, errors } = useForm({
        identity_mode: settings.identity_mode,
        comment_mode: settings.comment_mode,
        allow_back: settings.allow_back,
        inactivity_seconds: settings.inactivity_seconds,
        help_enabled: settings.help_enabled,
        introduction: settings.introduction ?? '',
        thank_you: settings.thank_you ?? '',
    })

    function submit(event: FormEvent): void {
        event.preventDefault()
        put(action, { preserveScroll: true })
    }

    return (
        <AdminShell>
            <Head title={t('interface.settings.title')} />

            <div className="page-header">
                <h1>{t('interface.settings.title')}</h1>
                <p className="hint mt-1">{survey.name}</p>
            </div>

            <StatusMessage />
            <ErrorSummary errors={errors} />

            <>
                    {/* Lo publicado no se toca. RF-AO-PUB-007: los cambios
                        van al borrador y solo llegan a la gente al publicar. */}
                    {publishedVersion !== null && (
                        <div className="alert alert-neutral mb-4" role="status">
                            {t('interface.settings.affects_draft', {
                                draft: settings.version_number,
                                published: publishedVersion,
                            })}
                        </div>
                    )}

                    <div className="card card-pad max-w-140">
                        <form onSubmit={submit}>
                            <div className="field">
                                <label htmlFor="identity_mode">
                                    {t('interface.settings.identity_mode')}
                                </label>
                                <select
                                    id="identity_mode"
                                    className="input"
                                    value={data.identity_mode}
                                    aria-describedby="identity-hint"
                                    onChange={(e) => setData('identity_mode', e.target.value)}
                                >
                                    {identityModes.map((mode) => (
                                        <option key={mode} value={mode}>
                                            {t(`interface.settings.identity_${mode}`)}
                                        </option>
                                    ))}
                                </select>
                                <span id="identity-hint" className="hint">
                                    {t('interface.settings.identity_hint')}
                                </span>
                            </div>

                            <div className="field">
                                <label htmlFor="comment_mode">
                                    {t('interface.settings.comment_mode')}
                                </label>
                                <select
                                    id="comment_mode"
                                    className="input"
                                    value={data.comment_mode}
                                    onChange={(e) => setData('comment_mode', e.target.value)}
                                >
                                    {commentModes.map((mode) => (
                                        <option key={mode} value={mode}>
                                            {t(`interface.settings.comment_${mode}`)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="field">
                                <label htmlFor="inactivity_seconds">
                                    {t('interface.settings.inactivity')}
                                </label>
                                <input
                                    id="inactivity_seconds"
                                    type="number"
                                    className="input"
                                    min={10}
                                    max={600}
                                    value={data.inactivity_seconds}
                                    aria-describedby="inactivity-hint"
                                    aria-invalid={errors.inactivity_seconds ? true : undefined}
                                    onChange={(e) =>
                                        setData('inactivity_seconds', Number(e.target.value))
                                    }
                                />
                                <span id="inactivity-hint" className="hint">
                                    {t('interface.settings.inactivity_hint')}
                                </span>
                                {errors.inactivity_seconds && (
                                    <span className="error">{errors.inactivity_seconds}</span>
                                )}
                            </div>

                            <label className="text-ink-muted flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.allow_back}
                                    onChange={(e) => setData('allow_back', e.target.checked)}
                                />
                                {t('interface.settings.allow_back')}
                            </label>

                            <label className="text-ink-muted mt-2 flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.help_enabled}
                                    onChange={(e) => setData('help_enabled', e.target.checked)}
                                />
                                {t('interface.settings.help_enabled')}
                            </label>

                            <div className="field mt-4">
                                <label htmlFor="introduction">
                                    {t('interface.settings.introduction')}
                                </label>
                                <textarea
                                    id="introduction"
                                    className="input input-grow"
                                    maxLength={1000}
                                    value={data.introduction}
                                    onChange={(e) => setData('introduction', e.target.value)}
                                />
                            </div>

                            <div className="field">
                                <label htmlFor="thank_you">{t('interface.settings.thank_you')}</label>
                                <textarea
                                    id="thank_you"
                                    className="input input-grow"
                                    maxLength={1000}
                                    value={data.thank_you}
                                    onChange={(e) => setData('thank_you', e.target.value)}
                                />
                            </div>

                            <div className="actions">
                                <button type="submit" className="btn btn-primary" disabled={processing}>
                                    {t('interface.settings.save')}
                                </button>

                                <Link href={backUrl} className="btn btn-ghost">
                                    {t('interface.settings.back')}
                                </Link>
                            </div>
                        </form>
                    </div>
            </>
        </AdminShell>
    )
}
