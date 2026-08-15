import { Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import StatusMessage from '@/Components/StatusMessage'
import AuthShell from '@/Layouts/AuthShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    action: string
    loginUrl: string
}

export default function ForgotPassword({ action, loginUrl }: Props) {
    const t = useTranslate()

    const { data, setData, post, processing, errors } = useForm({ email: '' })

    function submit(event: FormEvent): void {
        event.preventDefault()
        post(action)
    }

    return (
        <AuthShell
            title={t('interface.forgot.title')}
            subtitle={t('interface.forgot.subtitle')}
        >
            <h1 className="text-xl">{t('interface.forgot.title')}</h1>
            <p className="hint mt-1 mb-4">{t('interface.forgot.help')}</p>

            {/*
                El mensaje de exito es el mismo exista o no la cuenta.
                RF-AUT-009: distinguirlos convertiria esta pantalla en un
                comprobador de que direcciones estan registradas.
            */}
            <StatusMessage />
            <ErrorSummary errors={errors} />

            <form onSubmit={submit}>
                <div className="field">
                    <label htmlFor="email">{t('interface.forgot.email')}</label>
                    <input
                        id="email"
                        type="email"
                        className="input"
                        value={data.email}
                        autoComplete="username"
                        inputMode="email"
                        required
                        aria-invalid={errors.email ? true : undefined}
                        aria-describedby={errors.email ? 'email-error' : undefined}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    {errors.email && (
                        <span id="email-error" className="error">
                            {errors.email}
                        </span>
                    )}
                </div>

                <button
                    type="submit"
                    className="btn btn-primary btn-lg btn-block"
                    disabled={processing}
                >
                    {t('interface.forgot.submit')}
                </button>

                <p className="mt-4 text-center">
                    <Link href={loginUrl} className="text-primary text-sm">
                        {t('interface.forgot.back')}
                    </Link>
                </p>
            </form>
        </AuthShell>
    )
}
