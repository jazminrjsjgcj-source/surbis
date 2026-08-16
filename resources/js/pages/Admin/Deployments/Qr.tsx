import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'

import ConfirmDialog from '@/Components/ConfirmDialog'
import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    deployment: {
        ulid: string
        survey_name: string
        channel: string
        is_applying: boolean
    }
    token: string | null
    url: string | null
    qrUrl: string
    regenerateUrl: string
    backUrl: string
}

/**
 * QR y enlace publico. RF-AO-DEP-008, 009 y 010.
 */
export default function Qr({ deployment, token, url, qrUrl, regenerateUrl, backUrl }: Props) {
    const t = useTranslate()
    const [confirmando, setConfirmando] = useState(false)

    return (
        <AdminShell>
            <Head title={t('interface.qr.title')} />

            <div className="page-header">
                <Link href={backUrl} className="text-primary text-sm">
                    {t('interface.deployments.title')}
                </Link>

                <h1 className="mt-1">{t('interface.qr.title')}</h1>
                <p className="hint mt-1">{deployment.survey_name}</p>
            </div>

            <StatusMessage />

            {/* Si no esta aplicando, el QR lleva a una pantalla que dice que
                no. Avisarlo aqui evita imprimir un cartel inutil. */}
            {!deployment.is_applying && (
                <div className="alert alert-neutral mb-4 max-w-140" role="status">
                    {t('interface.qr.not_applying')}
                </div>
            )}

            <div className="card card-pad max-w-140">
                {url === null ? (
                    /*
                     * Sin token en claro no hay enlace ni QR.
                     *
                     * En la base solo esta su hash: el token completo solo
                     * existe justo despues de crearlo o regenerarlo. Es
                     * incomodo a proposito —si se pudiera recuperar siempre,
                     * quien accediera a la base tendria todos los enlaces—.
                     */
                    <>
                        <p>{t('interface.qr.token_gone')}</p>

                        <div className="actions">
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={() => setConfirmando(true)}
                            >
                                {t('interface.qr.regenerate')}
                            </button>
                        </div>
                    </>
                ) : (
                    <>
                        <div className="field">
                            <label htmlFor="public-url">{t('interface.qr.url')}</label>
                            {/* readOnly y no disabled: un campo deshabilitado
                                no se puede seleccionar para copiar. */}
                            <input
                                id="public-url"
                                type="text"
                                className="input font-mono"
                                value={url}
                                readOnly
                                onFocus={(e) => e.currentTarget.select()}
                            />
                            <span className="hint">{t('interface.qr.url_help')}</span>
                        </div>

                        <div className="actions">
                            <a href={qrUrl} className="btn btn-primary" download>
                                {t('interface.qr.download')}
                            </a>

                            <button
                                type="button"
                                className="btn btn-ghost btn-danger ms-auto"
                                onClick={() => setConfirmando(true)}
                            >
                                {t('interface.qr.regenerate')}
                            </button>
                        </div>
                    </>
                )}
            </div>

            <ConfirmDialog
                open={confirmando}
                title={t('interface.qr.regenerate_title')}
                body={t('interface.qr.regenerate_body')}
                confirmLabel={t('interface.qr.regenerate')}
                destructive
                onConfirm={() => {
                    router.post(regenerateUrl)
                    setConfirmando(false)
                }}
                onCancel={() => setConfirmando(false)}
            />
        </AdminShell>
    )
}
