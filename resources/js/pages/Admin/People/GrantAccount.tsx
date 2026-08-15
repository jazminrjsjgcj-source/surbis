import { Head, Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    person: { ulid: string; name: string }
    roles: string[]
    action: string
    cancelUrl: string
}

/**
 * Dar cuenta a una persona que ya se evaluaba. D-021.
 *
 * Vincula la cuenta a la persona existente en lugar de crear un registro
 * nuevo: sus evaluaciones anteriores se conservan porque sigue siendo la
 * misma persona.
 */
export default function GrantAccount({ person, roles, action, cancelUrl }: Props) {
    const t = useTranslate()

    const { data, setData, post, processing, errors } = useForm({
        email: '',
        role: roles[0] ?? 'collaborator',
    })

    function submit(event: FormEvent): void {
        event.preventDefault()
        post(action)
    }

    return (
        <AdminShell>
            <Head title={t('interface.people.account_title')} />

            <div className="page-header">
                <h1>{t('interface.people.account_title')}</h1>
                <p className="hint mt-1">{person.name}</p>
            </div>

            <ErrorSummary errors={errors} />

            <div className="card card-pad max-w-140">
                <p className="mb-4">{t('interface.people.account_help')}</p>

                <form onSubmit={submit}>
                    <div className="field">
                        <label htmlFor="email">{t('interface.people.email')}</label>
                        <input
                            id="email"
                            type="email"
                            className="input"
                            value={data.email}
                            autoComplete="off"
                            required
                            aria-invalid={errors.email ? true : undefined}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        {errors.email && <span className="error">{errors.email}</span>}
                    </div>

                    <div className="field">
                        <label htmlFor="role">{t('interface.people.role')}</label>
                        <select
                            id="role"
                            className="input"
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value)}
                        >
                            {roles.map((role) => (
                                <option key={role} value={role}>
                                    {t(`interface.people.role_${role}`)}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="actions">
                        <button type="submit" className="btn btn-primary" disabled={processing}>
                            {t('interface.people.account_send')}
                        </button>

                        <Link href={cancelUrl} className="btn btn-ghost">
                            {t('interface.people.cancel')}
                        </Link>
                    </div>
                </form>
            </div>
        </AdminShell>
    )
}
