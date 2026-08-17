import { Head, Link, router, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    mfaEnabled: boolean
    recoveryCodes: string[] | null
    passwordSetByOther: boolean
    passwordUrl: string
    enableUrl: string
    disableUrl: string
    codesUrl: string
    backUrl: string
}

/**
 * Seguridad de la cuenta. P-011.
 *
 * Aqui vive el interruptor del segundo factor. Sin esta pantalla,
 * mfa_confirmed_at seria null para siempre y toda la verificacion en dos
 * pasos, codigo inalcanzable.
 */
export default function Security({
    passwordSetByOther,
    passwordUrl,
    mfaEnabled,
    recoveryCodes,
    enableUrl,
    disableUrl,
    codesUrl,
    backUrl,
}: Props) {
    const t = useTranslate()

    const pass = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    })

    function cambiarContrasena(event: FormEvent): void {
        event.preventDefault()

        /*
         * onSuccess vacia los campos.
         *
         * Dejar la contrasena escrita en la pantalla tras guardarla es
         * exactamente lo que no se quiere en un ordenador compartido.
         */
        pass.post(passwordUrl, {
            preserveScroll: true,
            onSuccess: () => pass.reset(),
        })
    }

    return (
        <AdminShell>
            <Head title={t('interface.security.title')} />

            <div className="page-header">
                <h1>{t('interface.security.title')}</h1>
            </div>

            <StatusMessage />

            <form onSubmit={cambiarContrasena} className="card card-pad mb-4 max-w-140">
                <h2 className="text-lg">{t('interface.security.password_heading')}</h2>

                {/*
                    El aviso cuando la puso otra persona.

                    No bloquea nada —el cambio es voluntario— pero mientras no
                    ocurra, esa contraseña la conocen dos.
                */}
                {passwordSetByOther && (
                    <div className="alert alert-neutral mt-3" role="status">
                        <p>{t('interface.security.set_by_other')}</p>
                    </div>
                )}

                <div className="field mt-3">
                    <label htmlFor="current_password">
                        {t('interface.security.current_password')}
                    </label>
                    <input
                        id="current_password"
                        type="password"
                        className="input"
                        autoComplete="current-password"
                        value={pass.data.current_password}
                        onChange={(e) => pass.setData('current_password', e.target.value)}
                    />
                    {pass.errors.current_password && (
                        <span className="error" role="alert">{pass.errors.current_password}</span>
                    )}
                </div>

                <div className="field">
                    <label htmlFor="password">{t('interface.security.new_password')}</label>
                    <input
                        id="password"
                        type="password"
                        className="input"
                        autoComplete="new-password"
                        value={pass.data.password}
                        onChange={(e) => pass.setData('password', e.target.value)}
                    />
                    {pass.errors.password && (
                        <span className="error" role="alert">{pass.errors.password}</span>
                    )}
                </div>

                <div className="field">
                    <label htmlFor="password_confirmation">
                        {t('interface.security.confirm_password')}
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        className="input"
                        autoComplete="new-password"
                        value={pass.data.password_confirmation}
                        onChange={(e) => pass.setData('password_confirmation', e.target.value)}
                    />
                </div>

                <button type="submit" className="btn btn-primary" disabled={pass.processing}>
                    {t('interface.security.change_password')}
                </button>
            </form>

            {/*
                Los codigos se muestran UNA sola vez: en la base solo queda su
                hash. Van arriba del todo y con role="alert" porque si esta
                persona cierra la pantalla sin copiarlos, nadie puede volver a
                ensenarselos.
            */}
            {recoveryCodes && recoveryCodes.length > 0 && (
                <div className="alert alert-neutral mb-4 max-w-140" role="alert">
                    <p className="alert-title">{t('interface.security.codes_heading')}</p>
                    <p>{t('interface.security.codes_help')}</p>

                    <ul className="code-list mt-3">
                        {recoveryCodes.map((code) => (
                            <li key={code}>{code}</li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="card card-pad max-w-140">
                <h2 className="text-lg">{t('interface.security.mfa_heading')}</h2>

                <p className="mt-1 mb-4">
                    {mfaEnabled
                        ? t('interface.security.mfa_on')
                        : t('interface.security.mfa_off')}
                </p>

                <div className="actions">
                    {mfaEnabled ? (
                        <>
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => router.post(codesUrl, {}, { preserveScroll: true })}
                            >
                                {t('interface.security.codes_regenerate')}
                            </button>

                            <button
                                type="button"
                                className="btn btn-ghost btn-danger"
                                onClick={() => router.delete(disableUrl, { preserveScroll: true })}
                            >
                                {t('interface.security.disable')}
                            </button>
                        </>
                    ) : (
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={() => router.post(enableUrl, {}, { preserveScroll: true })}
                        >
                            {t('interface.security.enable')}
                        </button>
                    )}

                    <Link href={backUrl} className="btn btn-ghost ms-auto">
                        {t('interface.security.back')}
                    </Link>
                </div>
            </div>
        </AdminShell>
    )
}
