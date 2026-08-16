import { Head, router } from '@inertiajs/react'

import { useTranslate } from '@/lib/translate'

interface Props {
    reason: string
    deviceName: string
    branchName: string | null
    retryUrl: string
}

/**
 * Estación no configurada. RF-COL-007 a 009.
 *
 * RNF-COL-004: no expone IDs internos, tokens ni rutas. Lo único técnico que
 * aparece es el nombre de la tableta, y eso es a propósito: es lo que hay que
 * decirle al administrador al llamarle.
 *
 * RF-COL-008: no deja corregir la configuración desde aquí. Quien está
 * delante es personal de ventanilla, no quien administra.
 */
export default function NotReady({ reason, deviceName, branchName, retryUrl }: Props) {
    const t = useTranslate()

    return (
        <div className="kiosk kiosk-centered">
            <Head title={t('interface.kiosk.not_ready_title')} />

            <div className="kiosk-panel">
                <h1 className="text-xl">{t('interface.kiosk.not_ready_title')}</h1>

                {/* En lenguaje claro y accionable (RNF-COL-005): qué falta y
                    a quién avisar. */}
                <p className="mt-2">{t(`interface.kiosk.${reason}`)}</p>

                <p className="hint mt-4">
                    {t('interface.kiosk.tell_admin', {
                        device: deviceName,
                        branch: branchName ?? '',
                    })}
                </p>

                <button
                    type="button"
                    className="btn btn-ghost btn-lg mt-4"
                    onClick={() => router.get(retryUrl)}
                >
                    {t('interface.kiosk.retry')}
                </button>
            </div>
        </div>
    )
}
