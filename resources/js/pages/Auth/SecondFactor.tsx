import { router, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import StatusMessage from '@/Components/StatusMessage'
import AuthShell from '@/Layouts/AuthShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    email: string
    action: string
    resendUrl: string
    cancelUrl: string
}

/**
 * Verificacion en dos pasos. RF-AUT-014 y 015.
 *
 * Va con AuthShell: aqui la sesion esta a medias —hay usuario pendiente, no
 * autenticado— y el marco de administracion contaria con una organizacion
 * activa que todavia no existe.
 */
export default function SecondFactor({ email, action, resendUrl, cancelUrl }: Props) {
    const t = useTranslate()

    const { data, setData, post, processing, errors } = useForm({ code: '' })

    function submit(event: FormEvent): void {
        event.preventDefault()
        post(action, { onFinish: () => setData('code', '') })
    }

    return (
        <AuthShell
            title={t('interface.second_factor.title')}
            subtitle={t('interface.second_factor.subtitle')}
        >
            <h1 className="text-xl">{t('interface.second_factor.title')}</h1>
            <p className="hint mt-1 mb-4">{t('interface.second_factor.help', { email })}</p>

            <StatusMessage />
            <ErrorSummary errors={errors} />

            <form onSubmit={submit}>
                <div className="field">
                    <label htmlFor="code">{t('interface.second_factor.code')}</label>
                    <input
                        id="code"
                        type="text"
                        className="input input-code"
                        value={data.code}
                        // one-time-code: el telefono ofrece el codigo del SMS
                        // o del correo sin tener que copiarlo a mano.
                        autoComplete="one-time-code"
                        inputMode="numeric"
                        autoFocus
                        required
                        aria-describedby={errors.code ? 'code-error' : 'code-hint'}
                        aria-invalid={errors.code ? true : undefined}
                        onChange={(e) => setData('code', e.target.value)}
                    />

                    {errors.code ? (
                        <span id="code-error" className="error">
                            {errors.code}
                        </span>
                    ) : (
                        <span id="code-hint" className="hint">
                            {t('interface.second_factor.code_hint')}
                        </span>
                    )}
                </div>

                <button
                    type="submit"
                    className="btn btn-primary btn-lg btn-block"
                    disabled={processing}
                >
                    {t('interface.second_factor.submit')}
                </button>
            </form>

            <div className="actions mt-4 justify-center">
                <button
                    type="button"
                    className="btn btn-ghost"
                    onClick={() => router.post(resendUrl)}
                >
                    {t('interface.second_factor.resend')}
                </button>

                {/* RF-AUT-015: cancelar cierra la sesion parcial. Sin esto,
                    quien no reciba el codigo se queda atrapado. */}
                <button
                    type="button"
                    className="btn btn-ghost"
                    onClick={() => router.post(cancelUrl)}
                >
                    {t('interface.second_factor.cancel')}
                </button>
            </div>
        </AuthShell>
    )
}
