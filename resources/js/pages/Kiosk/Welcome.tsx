import { Head } from '@inertiajs/react'
import { useCallback, useEffect, useRef, useState } from 'react'

import SurveyRenderer from '@/Components/Renderer/SurveyRenderer'
import { enqueue } from '@/lib/kioskQueue'
import { startSyncing } from '@/lib/kioskSync'
import type { Answers, RenderableSurvey } from '@/lib/renderer'
import { useTranslate } from '@/lib/translate'

interface Props {
    survey: RenderableSurvey
    submitUrl: string
    sessionUlid: string

    /** Para que las pendientes conserven la version con la que se contesto. */
    surveyVersionId: string
}

/**
 * El quiosco. RF-COL-010 a 013.
 *
 * Lo que ve un ciudadano de pie en una ventanilla. Sin navegación, sin nada
 * administrativo (RF-COL-011), y con el mismo SurveyRenderer que el enlace
 * público y la vista previa (RNF-COL-012).
 */
export default function Welcome({ survey, submitUrl, sessionUlid, surveyVersionId }: Props) {
    const t = useTranslate()

    const [empezada, setEmpezada] = useState(false)
    const [terminada, setTerminada] = useState(false)
    const [fallo, setFallo] = useState(false)

    /*
     * El UUID se genera al EMPEZAR cada respuesta, no al cargar la pantalla.
     *
     * Una tableta de ventanilla no se recarga en todo el día: si el UUID
     * fuera de la pantalla, la segunda persona reutilizaría el de la primera
     * y su respuesta se descartaría por idempotente.
     */
    const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID())

    const temporizador = useRef<number | null>(null)

    /**
     * Volver al principio BORRANDO lo contestado. RF-COL-012 · RNF-COL-008.
     *
     * Decisión del área usuaria: la captura parcial se elimina por completo.
     * Guardarla como incompleta dejaría media respuesta de alguien que se
     * fue, y dejarla en pantalla se la enseñaría a la persona siguiente.
     *
     * La clave nueva es parte del borrado: sin ella, la respuesta siguiente
     * heredaría la identidad de la anterior.
     */
    const reiniciar = useCallback((): void => {
        setEmpezada(false)
        setTerminada(false)
        setFallo(false)
        setIdempotencyKey(crypto.randomUUID())
    }, [])

    /*
     * El reloj de inactividad solo corre mientras se contesta.
     *
     * En la pantalla de bienvenida no hay nada que borrar, y reiniciarla cada
     * treinta segundos sería trabajo por nada.
     */
    useEffect(() => {
        if (! empezada || terminada) {
            return
        }

        function reiniciarReloj(): void {
            if (temporizador.current !== null) {
                window.clearTimeout(temporizador.current)
            }

            temporizador.current = window.setTimeout(
                reiniciar,
                survey.inactivitySeconds * 1000,
            )
        }

        reiniciarReloj()

        /*
         * Cualquier gesto cuenta como actividad. Sin esto, alguien que lee
         * despacio una pregunta larga vería desaparecer sus respuestas.
         */
        const eventos = ['pointerdown', 'keydown', 'touchstart'] as const

        for (const evento of eventos) {
            window.addEventListener(evento, reiniciarReloj)
        }

        return () => {
            if (temporizador.current !== null) {
                window.clearTimeout(temporizador.current)
            }

            for (const evento of eventos) {
                window.removeEventListener(evento, reiniciarReloj)
            }
        }
    }, [empezada, terminada, reiniciar, survey.inactivitySeconds])

    /*
     * Tras dar las gracias se vuelve solo, sin esperar a nadie.
     *
     * Quien acaba de contestar se va: si la pantalla se quedara ahí, la
     * persona siguiente encontraría el agradecimiento de otra.
     */
    useEffect(() => {
        if (! terminada) {
            return
        }

        const vuelta = window.setTimeout(reiniciar, 5000)

        return () => window.clearTimeout(vuelta)
    }, [terminada, reiniciar])

    /*
     * La sincronización corre en segundo plano mientras la pantalla vive.
     *
     * No bloquea nada: quien contesta ya recibió su "gracias" cuando la
     * respuesta quedó guardada en el dispositivo.
     */
    useEffect(() => startSyncing(submitUrl), [submitUrl])

    async function enviar(respuestas: Answers): Promise<void> {
        /*
         * PRIMERO se guarda en el dispositivo. Decisión del área usuaria,
         * 18 ago 2026.
         *
         * El "gracias" solo aparece cuando IndexedDB confirma la escritura,
         * no cuando responde el servidor. Es lo contrario de lo que hace casi
         * todo el mundo, y es lo correcto: sin conexión, esperar al servidor
         * dejaría a alguien mirando una pantalla que no avanza, y decir
         * "gracias" antes de guardar confirmaría una respuesta que puede
         * perderse.
         *
         * Si la escritura falla, NO se dice gracias: se dice que no se pudo
         * registrar y no se deja empezar otra.
         */
        try {
            await enqueue({
                idempotencyKey,
                sessionUlid,
                surveyVersionId,
                answers: respuestas,
                comment: null,
            })

            setTerminada(true)
        } catch {
            setFallo(true)

            return
        }

        // Y se intenta enviar ya, sin esperar al reloj. Si no hay conexión,
        // se queda en la cola y no pasa nada.
        void startSyncing(submitUrl, 0)
    }

    /*
     * No se pudo guardar: NO se dice gracias.
     *
     * Y no se ofrece "reintentar": si el almacenamiento local falla o está
     * lleno, reintentar dará el mismo resultado. Lo que hace falta es que
     * alguien lo mire, así que se manda a avisar.
     */
    if (fallo) {
        return (
            <div className="kiosk kiosk-centered">
                <Head title={survey.name} />

                <div className="kiosk-panel text-center">
                    <h1 className="text-xl">{t('interface.kiosk.not_saved_title')}</h1>
                    <p className="mt-2">{t('interface.kiosk.not_saved_body')}</p>
                </div>
            </div>
        )
    }

    if (terminada) {
        return (
            <div className="kiosk kiosk-centered">
                <Head title={survey.name} />

                <div className="kiosk-panel text-center">
                    <h1 className="text-2xl">{t('interface.kiosk.thanks')}</h1>
                    <p className="mt-2 text-lg">
                        {survey.thankYou ?? t('interface.kiosk.thanks_body')}
                    </p>
                </div>
            </div>
        )
    }

    if (! empezada) {
        return (
            <div className="kiosk kiosk-centered">
                <Head title={survey.name} />

                <div className="kiosk-panel text-center">
                    <h1 className="text-2xl">{survey.name}</h1>

                    {survey.introduction && <p className="mt-3 text-lg">{survey.introduction}</p>}

                    {/*
                        Una sola acción, grande y evidente. RNF-COL-006: tiene
                        que usarse sin capacitación, y quien pasa por una
                        ventanilla no lee instrucciones.
                    */}
                    <button
                        type="button"
                        className="btn btn-primary btn-kiosk mt-6"
                        onClick={() => setEmpezada(true)}
                    >
                        {t('interface.kiosk.begin')}
                    </button>
                </div>
            </div>
        )
    }

    return (
        <div className="kiosk">
            <Head title={survey.name} />

            <div className="kiosk-panel">
                <SurveyRenderer survey={survey} onComplete={(r) => void enviar(r)} />
            </div>
        </div>
    )
}
