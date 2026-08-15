import { Head, Link, router, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import BranchAreaPicker, { type BranchOption } from '@/Components/BranchAreaPicker'
import ErrorSummary from '@/Components/ErrorSummary'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Person {
    ulid: string
    first_name: string
    last_name: string
    employee_code: string | null
    branch_id: number | null
    area_id: number | null
    is_active: boolean
    archive_url: string
    activate_url: string
}

interface Props {
    person: Person | null
    branches: BranchOption[]
    action: string
    cancelUrl: string
}

/**
 * Persona evaluable: se evalua y NO usa el sistema. D-018.
 *
 * No tiene correo ni contrasena. Si algun dia necesita entrar, se le da
 * cuenta desde el listado y conserva su historial (D-021).
 */
export default function Person({ person, branches, action, cancelUrl }: Props) {
    const t = useTranslate()

    const { data, setData, post, put, processing, errors } = useForm({
        first_name: person?.first_name ?? '',
        last_name: person?.last_name ?? '',
        employee_code: person?.employee_code ?? '',
        branch_id: person?.branch_id ?? null,
        area_id: person?.area_id ?? null,
    })

    function submit(event: FormEvent): void {
        event.preventDefault()

        if (person) {
            put(action)
        } else {
            post(action)
        }
    }

    return (
        <AdminShell>
            <Head title={person ? t('interface.people.person_edit_title') : t('interface.people.person_new')} />

            <div className="page-header">
                <h1>
                    {person
                        ? t('interface.people.person_edit_title')
                        : t('interface.people.person_new')}
                </h1>
                <p className="hint mt-1">{t('interface.people.person_help')}</p>
            </div>

            <ErrorSummary errors={errors} />

            <div className="card card-pad max-w-140">
                <form onSubmit={submit}>
                    <div className="field">
                        <label htmlFor="first_name">{t('interface.people.first_name')}</label>
                        <input
                            id="first_name"
                            type="text"
                            className="input"
                            value={data.first_name}
                            maxLength={80}
                            required
                            aria-invalid={errors.first_name ? true : undefined}
                            onChange={(e) => setData('first_name', e.target.value)}
                        />
                        {errors.first_name && <span className="error">{errors.first_name}</span>}
                    </div>

                    <div className="field">
                        <label htmlFor="last_name">{t('interface.people.last_name')}</label>
                        <input
                            id="last_name"
                            type="text"
                            className="input"
                            value={data.last_name}
                            maxLength={80}
                            required
                            aria-invalid={errors.last_name ? true : undefined}
                            onChange={(e) => setData('last_name', e.target.value)}
                        />
                        {errors.last_name && <span className="error">{errors.last_name}</span>}
                    </div>

                    <div className="field">
                        <label htmlFor="employee_code">{t('interface.people.employee_code')}</label>
                        <input
                            id="employee_code"
                            type="text"
                            className="input"
                            value={data.employee_code}
                            maxLength={40}
                            aria-describedby="code-hint"
                            aria-invalid={errors.employee_code ? true : undefined}
                            onChange={(e) => setData('employee_code', e.target.value)}
                        />
                        <span id="code-hint" className="hint">
                            {t('interface.people.employee_code_hint')}
                        </span>
                        {errors.employee_code && (
                            <span className="error">{errors.employee_code}</span>
                        )}
                    </div>

                    <BranchAreaPicker
                        branches={branches}
                        branchId={data.branch_id}
                        areaId={data.area_id}
                        idPrefix="person"
                        onChange={(branchId, areaId) => {
                            setData('branch_id', branchId)
                            setData('area_id', areaId)
                        }}
                    />

                    <div className="actions">
                        <button type="submit" className="btn btn-primary" disabled={processing}>
                            {t('interface.people.save')}
                        </button>

                        <Link href={cancelUrl} className="btn btn-ghost">
                            {t('interface.people.cancel')}
                        </Link>

                        {/* Archivar conserva las evaluaciones anteriores.
                            RF-GEN-010: no se borra nada que tenga historial. */}
                        {person && person.is_active && (
                            <button
                                type="button"
                                className="btn btn-ghost btn-danger ms-auto"
                                onClick={() => router.post(person.archive_url)}
                            >
                                {t('interface.people.archive')}
                            </button>
                        )}

                        {person && !person.is_active && (
                            <button
                                type="button"
                                className="btn btn-ghost ms-auto"
                                onClick={() => router.post(person.activate_url)}
                            >
                                {t('interface.people.activate')}
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </AdminShell>
    )
}
