import { useMemo, useState } from 'react'

import QuestionField from '@/Components/Renderer/QuestionField'
import {
    type Answers,
    type RenderableSurvey,
    problemWith,
    pruneHidden,
    visibleQuestions,
} from '@/lib/renderer'
import { useTranslate } from '@/lib/translate'

interface Props {
    survey: RenderableSurvey

    /**
     * Que hacer al terminar.
     *
     * El renderizador NO envia nada: acumula y avisa. Quien lo use decide si
     * eso va al servidor (Fase 9), a IndexedDB (Fase 10) o a ninguna parte
     * (vista previa).
     */
    onComplete: (answers: Answers) => void
}

/**
 * EL renderizador. RNF-COL-012.
 *
 * El mismo componente en quiosco, QR, enlace, widget y vista previa.
 * RNF-AO-BLD-004 y RNF-AO-PUB-002 prohiben implementar dos, y la razon es
 * concreta: en cuanto hay dos, la vista previa deja de predecir lo que vera
 * quien conteste, y entonces no sirve para nada.
 *
 * Lo que NO hace: enviar, guardar, ni decidir puntuaciones. RNF-COL-013 dice
 * que el navegador no decide puntuacion, colaborador, sucursal, organizacion
 * ni version. Aqui solo se recogen respuestas.
 */
export default function SurveyRenderer({ survey, onComplete }: Props) {
    const t = useTranslate()

    const [answers, setAnswers] = useState<Answers>({})
    const [index, setIndex] = useState(0)
    const [intentado, setIntentado] = useState(false)

    /*
     * Las visibles se recalculan con cada respuesta.
     *
     * Contestar puede hacer aparecer o desaparecer preguntas, asi que la
     * lista no es fija: useMemo evita rehacerla en cada pulsacion de tecla.
     */
    const visibles = useMemo(
        () => visibleQuestions(survey.questions, answers),
        [survey.questions, answers],
    )

    const stepped = survey.layout === 'stepped'
    const actual = visibles[index]

    function responder(ulid: string, valor: string | string[] | null): void {
        setAnswers((previas) => {
            const nuevas = { ...previas, [ulid]: valor }

            /*
             * Al cambiar una respuesta, las de las preguntas que dejan de
             * verse se retiran.
             *
             * Si alguien contesta "si", se le muestra el seguimiento, lo
             * contesta y luego cambia a "no", esa contestacion ya no tiene
             * sentido: seria una respuesta a una pregunta que no se le hizo.
             */
            return pruneHidden(survey.questions, nuevas)
        })

        setIntentado(false)
    }

    function avanzar(): void {
        if (actual === undefined) {
            return
        }

        // RF-COL-016: obligatoriedad y limites ANTES de avanzar.
        if (problemWith(actual, answers[actual.ulid]) !== null) {
            setIntentado(true)

            return
        }

        if (index + 1 < visibles.length) {
            setIndex(index + 1)

            return
        }

        onComplete(answers)
    }

    function enviarTodo(): void {
        const conProblema = visibles.some(
            (q) => problemWith(q, answers[q.ulid]) !== null,
        )

        if (conProblema) {
            setIntentado(true)

            return
        }

        onComplete(answers)
    }

    if (visibles.length === 0) {
        return <p className="renderer-empty">{t('interface.renderer.no_questions')}</p>
    }

    // Una pregunta por pantalla: quiosco y QR.
    if (stepped) {
        if (actual === undefined) {
            return null
        }

        const problema = intentado ? problemWith(actual, answers[actual.ulid]) : null

        return (
            <div className="renderer">
                {/*
                    El progreso en palabras y no solo con una barra: quien usa
                    lector de pantalla tambien tiene que saber cuanto queda.
                */}
                <p className="renderer-progress" role="status">
                    {t('interface.renderer.progress', {
                        current: index + 1,
                        total: visibles.length,
                    })}
                </p>

                <QuestionField
                    question={actual}
                    value={answers[actual.ulid] ?? null}
                    onChange={(valor) => responder(actual.ulid, valor)}
                />

                {problema && (
                    <p className="error" role="alert">
                        {t(`interface.renderer.problem_${problema.key}`, problema.values)}
                    </p>
                )}

                <div className="renderer-actions">
                    {/*
                        Retroceder solo si la configuracion lo permite
                        (RF-COL-019) y no en la primera.
                    */}
                    {survey.allowBack && index > 0 && (
                        <button
                            type="button"
                            className="btn btn-ghost btn-lg"
                            onClick={() => setIndex(index - 1)}
                        >
                            {t('interface.renderer.back')}
                        </button>
                    )}

                    <button
                        type="button"
                        className="btn btn-primary btn-lg ms-auto"
                        onClick={avanzar}
                    >
                        {index + 1 === visibles.length
                            ? t('interface.renderer.finish')
                            : t('interface.renderer.next')}
                    </button>
                </div>
            </div>
        )
    }

    // Todas a la vez: enlace publico y widget.
    return (
        <div className="renderer">
            {visibles.map((question) => {
                const problema = intentado ? problemWith(question, answers[question.ulid]) : null

                return (
                    <div key={question.ulid}>
                        <QuestionField
                            question={question}
                            value={answers[question.ulid] ?? null}
                            onChange={(valor) => responder(question.ulid, valor)}
                        />

                        {problema && (
                            <p className="error" role="alert">
                                {t(`interface.renderer.problem_${problema.key}`, problema.values)}
                            </p>
                        )}
                    </div>
                )
            })}

            <div className="renderer-actions">
                <button
                    type="button"
                    className="btn btn-primary btn-lg ms-auto"
                    onClick={enviarTodo}
                >
                    {t('interface.renderer.finish')}
                </button>
            </div>
        </div>
    )
}
