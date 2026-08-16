import { Head } from '@inertiajs/react'

import AuthShell from '@/Layouts/AuthShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    available: boolean
    surveyName: string | null
}

/**
 * La puerta publica de una encuesta.
 *
 * De momento solo dice si el enlace vale. El renderizador llega en la Fase 7;
 * esta pantalla existe ahora para que el QR pueda escanearse y verificarse.
 */
export default function Survey({ available, surveyName }: Props) {
    const t = useTranslate()

    return (
        <AuthShell title={surveyName ?? t('interface.public.title')}>
            <Head title={surveyName ?? t('interface.public.title')} />

            <div className="text-center">
                {available ? (
                    <>
                        <h1 className="text-xl">{surveyName}</h1>
                        <p className="mt-2">{t('interface.public.coming_soon')}</p>
                    </>
                ) : (
                    /*
                     * Un enlace inexistente y uno caducado dicen lo MISMO.
                     *
                     * Distinguirlos convertiria la URL en un comprobador:
                     * probando tokens se sabria cuales existen.
                     */
                    <>
                        <h1 className="text-xl">{t('interface.public.unavailable_title')}</h1>
                        <p className="mt-2">{t('interface.public.unavailable_body')}</p>
                    </>
                )}
            </div>
        </AuthShell>
    )
}
