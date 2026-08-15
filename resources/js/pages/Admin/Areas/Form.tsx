import { Head, Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    branch: { ulid: string; name: string }
    area: { ulid: string; name: string; code: string } | null
    action: string
    cancelUrl: string
}

export default function Form({ branch, area, action, cancelUrl }: Props) {
    const t = useTranslate()

    const { data, setData, post, put, processing, errors } = useForm({
        name: area?.name ?? '',
        code: area?.code ?? '',
    })

    function submit(event: FormEvent): void {
        event.preventDefault()

        if (area) {
            put(action)
        } else {
            post(action)
        }
    }

    return (
        <AdminShell>
            <Head title={area ? t('interface.areas.edit_title') : t('interface.areas.new')} />

            <div className="page-header">
                <Link href={cancelUrl} className="text-primary text-sm">
                    {branch.name}
                </Link>

                <h1 className="mt-1">
                    {area ? t('interface.areas.edit_title') : t('interface.areas.new')}
                </h1>
            </div>

            <ErrorSummary errors={errors} />

            <div className="card card-pad max-w-140">
                <form onSubmit={submit}>
                    <div className="field">
                        <label htmlFor="name">{t('interface.areas.name')}</label>
                        <input
                            id="name"
                            type="text"
                            className="input"
                            value={data.name}
                            maxLength={120}
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
                        <label htmlFor="code">{t('interface.areas.code')}</label>
                        <input
                            id="code"
                            type="text"
                            className="input"
                            value={data.code}
                            maxLength={32}
                            required
                            aria-invalid={errors.code ? true : undefined}
                            aria-describedby={errors.code ? 'code-error' : 'code-hint'}
                            onChange={(e) => setData('code', e.target.value)}
                        />
                        {/* El codigo es unico dentro de la sucursal, no de la
                            organizacion: dos sedes pueden tener su ventanilla 1. */}
                        <span id="code-hint" className="hint">
                            {t('interface.areas.code_hint')}
                        </span>
                        {errors.code && (
                            <span id="code-error" className="error">
                                {errors.code}
                            </span>
                        )}
                    </div>

                    <div className="actions">
                        <button type="submit" className="btn btn-primary" disabled={processing}>
                            {t('interface.areas.save')}
                        </button>

                        <Link href={cancelUrl} className="btn btn-ghost">
                            {t('interface.areas.cancel')}
                        </Link>
                    </div>
                </form>
            </div>
        </AdminShell>
    )
}
