import { Head, Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import BranchAreaPicker, { type BranchOption } from '@/Components/BranchAreaPicker'
import ErrorSummary from '@/Components/ErrorSummary'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    branches: BranchOption[]
    roles: string[]
    action: string
    cancelUrl: string
}

/**
 * Invitar a alguien. D-019: se manda una liga de un solo uso y la membresia
 * nace SUSPENDIDA; se activa cuando la persona define su contrasena.
 */
export default function Invite({ branches, roles, action, cancelUrl }: Props) {
    const t = useTranslate()

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        role: roles[0] ?? 'collaborator',
        branch_id: null as number | null,
        area_id: null as number | null,
    })

    function submit(event: FormEvent): void {
        event.preventDefault()
        post(action)
    }

    return (
        <AdminShell>
            <Head title={t('interface.people.invite_title')} />

            <div className="page-header">
                <h1>{t('interface.people.invite_title')}</h1>
                <p className="hint mt-1">{t('interface.people.invite_help')}</p>
            </div>

            <ErrorSummary errors={errors} />

            <div className="card card-pad max-w-140">
                <form onSubmit={submit}>
                    <div className="field">
                        <label htmlFor="name">{t('interface.people.name')}</label>
                        <input
                            id="name"
                            type="text"
                            className="input"
                            value={data.name}
                            maxLength={160}
                            required
                            aria-invalid={errors.name ? true : undefined}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        {errors.name && <span className="error">{errors.name}</span>}
                    </div>

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

                    <BranchAreaPicker
                        branches={branches}
                        branchId={data.branch_id}
                        areaId={data.area_id}
                        idPrefix="invite"
                        onChange={(branchId, areaId) => {
                            setData('branch_id', branchId)
                            setData('area_id', areaId)
                        }}
                    />

                    <div className="actions">
                        <button type="submit" className="btn btn-primary" disabled={processing}>
                            {t('interface.people.invite_send')}
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
