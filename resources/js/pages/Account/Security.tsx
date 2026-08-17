import { Head, Link, router } from '@inertiajs/react'

import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    mfaEnabled: boolean
    recoveryCodes: string[] | null
    available: boolean
    unavailableReason: string | null
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
    available,
    unavailableReason,
    mfaEnabled,
    recoveryCodes,
    enableUrl,
    disableUrl,
    codesUrl,
    backUrl,
}: Props) {
    const t = useTranslate()

    return (
        <AdminShell>
            <Head title={t('interface.security.title')} />

            <div className="page-header">
                <h1>{t('interface.security.title')}</h1>
            </div>

            <StatusMessage />

            {/*
                Sin correo configurado NO se puede activar. P-013.

                Activarlo dejaria a esta persona fuera de su propia cuenta: la
                pantalla le pediria un codigo que nunca va a recibir.
            */}
            {!available && (
                <div className="alert alert-neutral mb-4 max-w-140" role="status">
                    <p className="alert-title">{t('interface.security.unavailable_title')}</p>
                    <p>{t(`interface.security.unavailable_${unavailableReason}`)}</p>
                </div>
            )}

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
