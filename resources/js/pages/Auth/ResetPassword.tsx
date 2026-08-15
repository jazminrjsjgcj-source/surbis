import { useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import AuthShell from '@/Layouts/AuthShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    token: string
    email: string
    action: string
    minLength: number
}

export default function ResetPassword({ token, email, action, minLength }: Props) {
    const t = useTranslate()

    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    })

    function submit(event: FormEvent): void {
        event.preventDefault()

        // Las dos contrasenas se limpian pase lo que pase: si el envio falla,
        // dejarlas escritas en el navegador no aporta nada y las deja a la
        // vista de quien pase por detras.
        post(action, {
            onFinish: () => {
                setData('password', '')
                setData('password_confirmation', '')
            },
        })
    }

    return (
        <AuthShell title={t('interface.reset.title')} subtitle={t('interface.reset.subtitle')}>
            <h1 className="text-xl">{t('interface.reset.title')}</h1>
            <p className="hint mt-1 mb-4">{t('interface.reset.help')}</p>

            <ErrorSummary errors={errors} />

            <form onSubmit={submit}>
                <div className="field">
                    <label htmlFor="email">{t('interface.reset.email')}</label>
                    <input
                        id="email"
                        type="email"
                        className="input"
                        value={data.email}
                        autoComplete="username"
                        required
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </div>

                <div className="field">
                    <label htmlFor="password">{t('interface.reset.password')}</label>
                    <input
                        id="password"
                        type="password"
                        className="input"
                        value={data.password}
                        autoComplete="new-password"
                        required
                        aria-describedby="policy"
                        aria-invalid={errors.password ? true : undefined}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {/* El minimo viene del servidor, de la misma constante
                        que aplica la validacion. Escribirlo aqui seria una
                        segunda verdad: la pantalla podria prometer 8 mientras
                        el servidor exige 12. */}
                    <span id="policy" className="hint">
                        {t('interface.password.policy', { min: minLength })}
                    </span>
                    {errors.password && <span className="error">{errors.password}</span>}
                </div>

                <div className="field">
                    <label htmlFor="password_confirmation">
                        {t('interface.reset.confirmation')}
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        className="input"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        required
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </div>

                <button
                    type="submit"
                    className="btn btn-primary btn-lg btn-block"
                    disabled={processing}
                >
                    {t('interface.reset.submit')}
                </button>
            </form>
        </AuthShell>
    )
}
