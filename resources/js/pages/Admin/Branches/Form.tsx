import { Head, Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Branch {
    ulid: string
    name: string
    code: string
}

interface Props {
    branch: Branch | null
    action: string
    cancelUrl: string
}

export default function Form({ branch, action, cancelUrl }: Props) {
    const t = useTranslate()

    const { data, setData, post, put, processing, errors } = useForm({
        name: branch?.name ?? '',
        code: branch?.code ?? '',
    })

    function submit(event: FormEvent): void {
        event.preventDefault()

        // PUT al editar, POST al crear. El metodo lo decide la existencia de
        // la sucursal, no un campo oculto: asi no hay forma de que la pantalla
        // y la ruta discrepen.
        if (branch) {
            put(action)
        } else {
            post(action)
        }
    }

    return (
        <AdminShell>
            <Head title={branch ? t('interface.branches.edit_title') : t('interface.branches.new')} />

            <div className="page-header">
                <h1>{branch ? t('interface.branches.edit_title') : t('interface.branches.new')}</h1>
            </div>

            <ErrorSummary errors={errors} />

            <div className="card card-pad max-w-140">
                <form onSubmit={submit}>
                    <div className="field">
                        <label htmlFor="name">{t('interface.branches.name')}</label>
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
                        <label htmlFor="code">{t('interface.branches.code')}</label>
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
                        <span id="code-hint" className="hint">
                            {t('interface.branches.code_hint')}
                        </span>
                        {errors.code && (
                            <span id="code-error" className="error">
                                {errors.code}
                            </span>
                        )}
                    </div>

                    <div className="actions">
                        <button type="submit" className="btn btn-primary" disabled={processing}>
                            {t('interface.branches.save')}
                        </button>

                        <Link href={cancelUrl} className="btn btn-ghost">
                            {t('interface.branches.cancel')}
                        </Link>
                    </div>
                </form>
            </div>
        </AdminShell>
    )
}
