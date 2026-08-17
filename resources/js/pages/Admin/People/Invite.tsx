import { Head, Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import BranchAreaPicker, { type BranchOption } from '@/Components/BranchAreaPicker'
import ErrorSummary from '@/Components/ErrorSummary'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    canInvite: boolean
    branches: BranchOption[]
    roles: string[]
    action: string
    cancelUrl: string
}

/**
 * Invitar a alguien. D-019: se manda una liga de un solo uso y la membresia
 * nace SUSPENDIDA; se activa cuando la persona define su contrasena.
 */
export default function Invite({ canInvite, branches, roles, action, cancelUrl }: Props) {
    const t = useTranslate()

    const { data, setData, post, processing, errors } = useForm({
        password: '',
        password_confirmation: '',
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

                    {/*

                        Sin correo configurado, la contraseña la pone quien da de alta.


                        El enlace de invitación no llegaría a nadie, así que sin esto no

                        se podría dar de alta a ninguna persona. Tiene un coste: quien la

                        pone la conoce, y mientras no se cambie puede entrar como esa

                        persona.

                    */}

                    {!canInvite && (

                        <>

                            <div className="alert alert-neutral" role="status">

                                <p>{t('interface.people.no_mail')}</p>

                            </div>


                            <div className="field">

                                <label htmlFor="password">{t('interface.people.password')}</label>

                                <input

                                    id="password"

                                    type="password"

                                    className="input"

                                    autoComplete="new-password"

                                    value={data.password}

                                    onChange={(e) => setData('password', e.target.value)}

                                />

                                <span className="hint">{t('interface.people.password_help')}</span>

                                {errors.password && (

                                    <span className="error" role="alert">{errors.password}</span>

                                )}

                            </div>


                            <div className="field">

                                <label htmlFor="password_confirmation">

                                    {t('interface.people.password_confirm')}

                                </label>

                                <input

                                    id="password_confirmation"

                                    type="password"

                                    className="input"

                                    autoComplete="new-password"

                                    value={data.password_confirmation}

                                    onChange={(e) => setData('password_confirmation', e.target.value)}

                                />

                            </div>

                        </>

                    )}


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
