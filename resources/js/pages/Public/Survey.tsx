import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'

import IdentityStep, { type IdentityData } from '@/Components/Renderer/IdentityStep'
import SurveyRenderer from '@/Components/Renderer/SurveyRenderer'
import AuthShell from '@/Layouts/AuthShell'
import type { Answers, RenderableSurvey } from '@/lib/renderer'
import { useTranslate } from '@/lib/translate'

interface Props {
    available: boolean
    survey: RenderableSurvey | null
    submitUrl: string | null
    errors: Record<string, string>
}

interface PageProps {
    response_submitted?: boolean
    [key: string]: unknown
}

type Paso = 'questions' | 'identity' | 'done'

/**
 * La encuesta, tal como la contesta un ciudadano. RF-COL-020 a 024.
 *
 * Usa el MISMO SurveyRenderer que la vista previa y, en la Fase 8, el
 * quiosco (RNF-COL-012).
 */
export default function Survey({ available, survey, submitUrl, errors }: Props) {
    const t = useTranslate()
    const { props } = usePage<PageProps>()

    /*
     * El UUID se genera UNA VEZ, al montar la pantalla.
     *
     * Decision del area usuaria: lo genera el cliente. Es lo que hace posible
     * reintentar sin duplicar — si el envio llego y la confirmacion no, el
     * segundo intento trae el mismo UUID y el servidor devuelve la respuesta
     * que ya guardo.
     *
     * useState con funcion inicial y no useMemo: useMemo puede recalcularse,
     * y este valor no puede cambiar en toda la sesion.
     */
    const [idempotencyKey] = useState(() => crypto.randomUUID())

    const [paso, setPaso] = useState<Paso>('questions')
    const [answers, setAnswers] = useState<Answers>({})
    const [comment, setComment] = useState('')
    const [enviando, setEnviando] = useState(false)
    const [identity, setIdentity] = useState<IdentityData>({
        name: '',
        email: '',
        phone: '',
        consent: false,
    })

    if (props.response_submitted === true && paso !== 'done') {
        setPaso('done')
    }

    if (!available || survey === null || submitUrl === null) {
        /*
         * Un enlace inexistente y uno caducado dicen lo MISMO: distinguirlos
         * convertiria la URL en un comprobador de que tokens existen.
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

    /*
     * Si hace falta un paso final.
     *
     * Sin comentario ni identidad que pedir, se envia directo: un paso vacio
     * seria una pantalla de mas por nada, y en un quiosco cada pantalla
     * cuenta.
     */
    const pidePasoFinal =
        survey.commentMode !== 'disabled' ||
        ['identified', 'optional'].includes(survey.identityMode)

    function enviar(respuestas: Answers): void {
        setEnviando(true)

        router.post(
            submitUrl ?? '',
            {
                idempotency_key: idempotencyKey,
                answers: respuestas,
                comment: comment || null,
                identity,
            },
            {
                preserveScroll: true,
                onFinish: () => setEnviando(false),
            },
        )
    }

    if (paso === 'done') {
        return (
            <AuthShell title={survey.name}>
                <Head title={survey.name} />

                <div className="text-center">
                    <h1 className="text-xl">{t('interface.public.done_title')}</h1>
                    <p className="mt-2">{survey.thankYou ?? t('interface.public.done_body')}</p>
                </div>
            </AuthShell>
        )
    }

    return (
        <AuthShell title={survey.name}>
            <Head title={survey.name} />

            <h1 className="text-xl">{survey.name}</h1>

            {survey.introduction && paso === 'questions' && (
                <p className="mt-2 mb-4">{survey.introduction}</p>
            )}

            {errors.response && (
                <p className="error mt-2" role="alert">
                    {errors.response}
                </p>
            )}

            {paso === 'questions' ? (
                <SurveyRenderer
                    survey={survey}
                    onComplete={(respuestas) => {
                        setAnswers(respuestas)

                        if (pidePasoFinal) {
                            setPaso('identity')

                            return
                        }

                        enviar(respuestas)
                    }}
                />
            ) : (
                <>
                    <IdentityStep
                        mode={survey.identityMode}
                        commentMode={survey.commentMode}
                        comment={comment}
                        identity={identity}
                        onCommentChange={setComment}
                        onIdentityChange={setIdentity}
                    />

                    <div className="renderer-actions">
                        <button
                            type="button"
                            className="btn btn-primary btn-lg ms-auto"
                            disabled={enviando}
                            onClick={() => enviar(answers)}
                        >
                            {enviando
                                ? t('interface.public.sending')
                                : t('interface.public.send')}
                        </button>
                    </div>
                </>
            )}
        </AuthShell>
    )
}
