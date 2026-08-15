import { Head, Link, router, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import PublicationProblems, { type Problem } from '@/Components/PublicationProblems'
import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Version {
    number: number
    status: string
    date: string | null
}

interface Survey {
    ulid: string
    name: string
    description: string | null
    has_draft: boolean
}

interface Props {
    survey: Survey | null
    versions: Version[]
    action: string
    cancelUrl: string
    builderUrl: string | null
    settingsUrl: string | null
    draftUrl: string | null
    publishUrl: string | null
    problems: Problem[]
}

const MAX_NAME = 160
const MAX_DESCRIPTION = 500

export default function Form({
    survey,
    versions,
    action,
    cancelUrl,
    builderUrl,
    settingsUrl,
    draftUrl,
    publishUrl,
    problems,
}: Props) {
    const t = useTranslate()

    const { data, setData, post, put, processing, errors } = useForm({
        name: survey?.name ?? '',
        description: survey?.description ?? '',
    })

    function submit(event: FormEvent): void {
        event.preventDefault()

        if (survey) {
            put(action, { preserveScroll: true })
        } else {
            post(action)
        }
    }

    return (
        <AdminShell>
            <Head title={survey ? survey.name : t('interface.surveys.new')} />

            <div className="page-header">
                <h1>{survey ? survey.name : t('interface.surveys.new')}</h1>
            </div>

            <StatusMessage />
            <ErrorSummary errors={errors} />

            <div className="card card-pad max-w-140">
                <form onSubmit={submit}>
                    <div className="field">
                        <label htmlFor="name">{t('interface.surveys.name')}</label>
                        <input
                            id="name"
                            type="text"
                            className="input"
                            value={data.name}
                            maxLength={MAX_NAME}
                            required
                            aria-invalid={errors.name ? true : undefined}
                            aria-describedby={errors.name ? 'name-error' : undefined}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        {errors.name && (
                            <span id="name-error" className="error">
                                {errors.name}
                            </span>
                        )}
                    </div>

                    <div className="field">
                        <label htmlFor="description">{t('interface.surveys.description')}</label>
                        <textarea
                            id="description"
                            className="input input-grow"
                            value={data.description}
                            maxLength={MAX_DESCRIPTION}
                            aria-describedby="description-hint"
                            onChange={(e) => setData('description', e.target.value)}
                        />
                        <span id="description-hint" className="hint">
                            {t('interface.surveys.description_hint')}
                        </span>
                    </div>

                    <div className="actions">
                        <button type="submit" className="btn btn-primary" disabled={processing}>
                            {t('interface.surveys.save')}
                        </button>

                        <Link href={cancelUrl} className="btn btn-ghost">
                            {t('interface.surveys.cancel')}
                        </Link>
                    </div>
                </form>
            </div>

            {/* Las dos puertas de la encuesta. Solo existen si la encuesta ya
                se creo: ofrecerlas antes llevaria a pantallas sin destino. */}
            {survey && (
                <div className="card card-pad mt-4 max-w-140">
                    <div className="actions">
                        {builderUrl && (
                            <Link href={builderUrl} className="btn btn-primary">
                                {t('interface.surveys.builder_link')}
                            </Link>
                        )}

                        {settingsUrl && (
                            <Link href={settingsUrl} className="btn btn-ghost">
                                {t('interface.settings.link')}
                            </Link>
                        )}

                        {/*
                            Abrir borrador solo cuando NO hay uno.
                            RF-AO-SUR-007: el indice unico de la base impide
                            dos borradores a la vez, asi que ofrecerlo con uno
                            abierto seria un boton que siempre falla.
                        */}
                        {draftUrl && !survey.has_draft && (
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => router.post(draftUrl, {}, { preserveScroll: true })}
                            >
                                {t('interface.surveys.open_draft')}
                            </button>
                        )}
                    </div>
                </div>
            )}

            {/* Publicar. RF-AO-PUB-005 a 007. */}
            {survey && publishUrl && (
                <div className="card card-pad mt-4 max-w-140">
                    <h2 className="text-lg">{t('interface.surveys.publish_title')}</h2>
                    <p className="hint mt-1 mb-3">{t('interface.surveys.publish_help')}</p>

                    <PublicationProblems problems={problems} />

                    {/*
                        El boton se deshabilita si hay problemas.
                        Deshabilitarlo NO es la proteccion —esa la da
                        PublishVersion, que vuelve a comprobar dentro de una
                        transaccion— sino evitar un intento que ya se sabe que
                        va a fallar.
                    */}
                    <button
                        type="button"
                        className="btn btn-primary"
                        disabled={problems.length > 0}
                        onClick={() => router.post(publishUrl, {}, { preserveScroll: true })}
                    >
                        {t('interface.surveys.publish')}
                    </button>

                    {problems.length === 0 && (
                        <p className="hint mt-3">{t('interface.surveys.publish_warning')}</p>
                    )}
                </div>
            )}

            {versions.length > 0 && (
                <div className="card card-pad mt-4 max-w-140">
                    <h2 className="text-lg">{t('interface.surveys.versions')}</h2>

                    <ul className="mt-2">
                        {versions.map((version) => (
                            <li key={version.number} className="flex items-center gap-3 py-1">
                                <span className="font-mono text-sm">{version.number}</span>

                                <span className={`badge ${version.status === 'published' ? 'badge-active' : ''}`}>
                                    {t(`interface.surveys.version_${version.status}`)}
                                </span>

                                <span className="hint">{version.date}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </AdminShell>
    )
}
