import { Head, Link, useForm } from '@inertiajs/react'
import { useState } from 'react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Answer {
    question: string
    type: string
    option: string | null
    value: string | null
    score: number | null
}

interface Props {
    response: {
        ulid: string
        submittedAt: string | null
        surveyName: string
        versionNumber: number
        branchName: string | null
        areaName: string | null
        channel: string
        score: number | null
        maxScore: number | null
        comment: string | null
        isInvalidated: boolean
        invalidationReason: string | null
        answers: Answer[]
        identityMode: string
        hasIdentity: boolean
        canViewIdentity: boolean
        identity: { name: string | null; email: string | null; phone: string | null } | null
    }
    invalidateUrl: string
    backUrl: string
}

export default function Show({ response, invalidateUrl, backUrl }: Props) {
    const t = useTranslate()
    const [invalidando, setInvalidando] = useState(false)

    const { data, setData, post, processing, errors } = useForm({ reason: '' })

    function invalidar(event: FormEvent): void {
        event.preventDefault()
        post(invalidateUrl, { onSuccess: () => setInvalidando(false) })
    }

    return (
        <AdminShell>
            <Head title={t('interface.responses.detail')} />

            <div className="page-header">
                <Link href={backUrl} className="text-primary text-sm">
                    {t('interface.responses.title')}
                </Link>

                <h1 className="mt-1">{response.surveyName}</h1>
                <p className="hint mt-1">
                    {response.submittedAt} ·{' '}
                    {t('interface.responses.version', { number: response.versionNumber })}
                    {response.branchName && ` · ${response.branchName}`}
                </p>
            </div>

            <StatusMessage />

            {response.isInvalidated && (
                <div className="alert alert-neutral mb-4 max-w-140" role="status">
                    <p className="alert-title">{t('interface.responses.invalidated_title')}</p>
                    <p>{response.invalidationReason}</p>
                </div>
            )}

            <div className="card card-pad max-w-140">
                <h2 className="text-lg">{t('interface.responses.answers')}</h2>

                {/*
                    De la versión HISTÓRICA: si la encuesta cambió después,
                    esta respuesta contestó a lo que se preguntó entonces.
                */}
                <ol className="mt-3">
                    {response.answers.map((answer, i) => (
                        <li key={i} className="border-line border-b py-3 last:border-0">
                            <span className="block font-semibold">{answer.question}</span>

                            <span className="block">
                                {answer.option ?? answer.value ?? (
                                    <span className="hint">
                                        {t('interface.responses.no_answer')}
                                    </span>
                                )}
                            </span>

                            {answer.score !== null && (
                                <span className="hint">
                                    {t('interface.responses.points', { score: answer.score })}
                                </span>
                            )}
                        </li>
                    ))}
                </ol>

                {response.score !== null && (
                    <p className="mt-3 font-semibold">
                        {t('interface.responses.total_score', {
                            score: response.score,
                            max: response.maxScore ?? 0,
                        })}
                    </p>
                )}
            </div>

            {response.comment && (
                <div className="card card-pad mt-4 max-w-140">
                    <h2 className="text-lg">{t('interface.responses.comment')}</h2>

                    {/*
                        Como TEXTO, nunca como HTML. RNF-AO-RES-007.
                        React escapa por defecto: lo que NO hay que hacer aquí
                        es dangerouslySetInnerHTML.
                    */}
                    <p className="mt-2">{response.comment}</p>
                </div>
            )}

            <div className="card card-pad mt-4 max-w-140">
                <h2 className="text-lg">{t('interface.responses.identity')}</h2>

                <p className="hint mt-1">
                    {t(`interface.responses.identity_${response.identityMode}`)}
                </p>

                {/*
                    RF-AO-RES-004: se dice SI es identificada, sin revelar los
                    datos a quien no puede verlos.
                */}
                {response.hasIdentity && ! response.canViewIdentity && (
                    <p className="mt-2">{t('interface.responses.identity_locked')}</p>
                )}

                {response.identity && (
                    <dl className="mt-2">
                        {response.identity.name && (
                            <>
                                <dt className="hint">{t('interface.public.name')}</dt>
                                <dd>{response.identity.name}</dd>
                            </>
                        )}
                        {response.identity.email && (
                            <>
                                <dt className="hint">{t('interface.public.email')}</dt>
                                <dd>{response.identity.email}</dd>
                            </>
                        )}
                        {response.identity.phone && (
                            <>
                                <dt className="hint">{t('interface.public.phone')}</dt>
                                <dd>{response.identity.phone}</dd>
                            </>
                        )}
                    </dl>
                )}
            </div>

            {/* RF-AO-RES-005: la respuesta no se edita. Solo se puede marcar. */}
            {! response.isInvalidated && (
                <div className="card card-pad mt-4 max-w-140">
                    {invalidando ? (
                        <form onSubmit={invalidar}>
                            <ErrorSummary errors={errors} />

                            <div className="field">
                                <label htmlFor="reason">
                                    {t('interface.responses.invalidate_reason')}
                                </label>
                                <textarea
                                    id="reason"
                                    className="input input-grow"
                                    value={data.reason}
                                    minLength={10}
                                    maxLength={500}
                                    required
                                    onChange={(e) => setData('reason', e.target.value)}
                                />
                                <span className="hint">
                                    {t('interface.responses.invalidate_help')}
                                </span>
                            </div>

                            <div className="actions">
                                <button
                                    type="submit"
                                    className="btn btn-ghost btn-danger"
                                    disabled={processing}
                                >
                                    {t('interface.responses.invalidate')}
                                </button>

                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    onClick={() => setInvalidando(false)}
                                >
                                    {t('interface.confirm.cancel')}
                                </button>
                            </div>
                        </form>
                    ) : (
                        <button
                            type="button"
                            className="btn btn-ghost btn-danger"
                            onClick={() => setInvalidando(true)}
                        >
                            {t('interface.responses.invalidate')}
                        </button>
                    )}
                </div>
            )}
        </AdminShell>
    )
}
