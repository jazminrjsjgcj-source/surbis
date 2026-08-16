import { Head } from '@inertiajs/react'
import { useState } from 'react'

import SurveyRenderer from '@/Components/Renderer/SurveyRenderer'
import AuthShell from '@/Layouts/AuthShell'
import type { Answers, RenderableSurvey } from '@/lib/renderer'
import { useTranslate } from '@/lib/translate'

interface Props {
    available: boolean
    survey: RenderableSurvey | null
}

/**
 * La encuesta, tal como la ve quien la contesta.
 *
 * Usa el MISMO SurveyRenderer que el quiosco y la vista previa (RNF-COL-012).
 *
 * De momento no envia: al terminar muestra el agradecimiento. Guardar la
 * respuesta es la Fase 9, y separar las dos cosas permite construir y probar
 * el recorrido entero sin la tabla de respuestas.
 */
export default function Survey({ available, survey }: Props) {
    const t = useTranslate()
    const [terminada, setTerminada] = useState(false)

    if (!available || survey === null) {
        /*
         * Un enlace inexistente y uno caducado dicen lo MISMO.
         *
         * Distinguirlos convertiria la URL en un comprobador: probando
         * tokens se sabria cuales existen.
         */
        return (
            <AuthShell title={t('interface.public.title')}>
                <Head title={t('interface.public.title')} />

                <div className="text-center">
                    <h1 className="text-xl">{t('interface.public.unavailable_title')}</h1>
                    <p className="mt-2">{t('interface.public.unavailable_body')}</p>
                </div>
            </AuthShell>
        )
    }

    if (terminada) {
        return (
            <AuthShell title={survey.name}>
                <Head title={survey.name} />

                <div className="text-center">
                    <h1 className="text-xl">{t('interface.public.done_title')}</h1>
                    <p className="mt-2">
                        {survey.thankYou ?? t('interface.public.done_body')}
                    </p>

                    {/* Aviso honesto: todavia no se guarda nada. Se retira
                        cuando la Fase 9 conecte el envio. */}
                    <p className="hint mt-4">{t('interface.public.not_saved_yet')}</p>
                </div>
            </AuthShell>
        )
    }

    return (
        <AuthShell title={survey.name}>
            <Head title={survey.name} />

            <h1 className="text-xl">{survey.name}</h1>

            {survey.introduction && <p className="mt-2 mb-4">{survey.introduction}</p>}

            <SurveyRenderer
                survey={survey}
                onComplete={(respuestas: Answers) => {
                    // Fase 9: aqui ira el envio. Hoy solo se pasa de pantalla.
                    void respuestas
                    setTerminada(true)
                }}
            />
        </AuthShell>
    )
}
