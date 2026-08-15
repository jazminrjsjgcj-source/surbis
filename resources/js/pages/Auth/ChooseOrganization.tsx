import { useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import AuthShell from '@/Layouts/AuthShell'
import { useTranslate } from '@/lib/translate'

interface Membership {
    ulid: string
    name: string
    role: string
}

interface Props {
    memberships: Membership[]
    action: string
}

/**
 * Elegir organizacion. P-004.
 *
 * Va con AuthShell y no con AdminShell: aqui la sesion esta a medias —hay
 * usuario, pero todavia no hay organizacion activa— y el marco de
 * administracion construye su navegacion contando con ella.
 */
export default function ChooseOrganization({ memberships, action }: Props) {
    const t = useTranslate()

    const { data, setData, post, processing, errors } = useForm({
        organization: memberships[0]?.ulid ?? '',
    })

    function submit(event: FormEvent): void {
        event.preventDefault()
        post(action)
    }

    return (
        <AuthShell
            title={t('interface.organizations.title')}
            subtitle={t('interface.organizations.subtitle')}
        >
            <h1 className="text-xl">{t('interface.organizations.title')}</h1>
            <p className="hint mt-1 mb-4">{t('interface.organizations.help')}</p>

            <ErrorSummary errors={errors} />

            <form onSubmit={submit}>
                {/* fieldset y legend porque son opciones excluyentes de una
                    misma pregunta: sin ellos, un lector de pantalla lee cinco
                    casillas sueltas sin decir de que eligen. */}
                <fieldset className="border-0 p-0">
                    <legend className="sr-only">{t('interface.organizations.title')}</legend>

                    {memberships.map((membership) => (
                        <label
                            key={membership.ulid}
                            className="panel flex items-start gap-3"
                            htmlFor={`org-${membership.ulid}`}
                        >
                            <input
                                id={`org-${membership.ulid}`}
                                type="radio"
                                name="organization"
                                value={membership.ulid}
                                checked={data.organization === membership.ulid}
                                onChange={(e) => setData('organization', e.target.value)}
                            />

                            <span>
                                <span className="block font-semibold">{membership.name}</span>
                                <span className="hint">
                                    {t(`interface.people.role_${membership.role}`)}
                                </span>
                            </span>
                        </label>
                    ))}
                </fieldset>

                <button
                    type="submit"
                    className="btn btn-primary btn-lg btn-block mt-4"
                    disabled={processing}
                >
                    {t('interface.organizations.submit')}
                </button>
            </form>
        </AuthShell>
    )
}
