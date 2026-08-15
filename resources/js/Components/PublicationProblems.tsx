import { useTranslate } from '@/lib/translate'

export interface Problem {
    key: string
    question_position: number | null
    replacements: Record<string, string | number>
}

interface Props {
    problems: Problem[]
}

/**
 * Lo que falta antes de poder publicar. RF-AO-PUB-006.
 *
 * Cada problema dice DONDE esta y QUE hacer, no solo que algo va mal. "Hay un
 * error en la encuesta" obligaria a revisarla entera para encontrarlo.
 *
 * role="status" y no "alert": esta lista aparece al cargar la pantalla, no
 * como reaccion a nada. Con "alert" interrumpiria la lectura cada vez que se
 * entra a una encuesta a medio construir, que es el estado normal mientras se
 * trabaja en ella.
 */
export default function PublicationProblems({ problems }: Props) {
    const t = useTranslate()

    if (problems.length === 0) {
        return null
    }

    return (
        <div className="alert alert-neutral mb-4 max-w-140" role="status">
            <p className="alert-title">{t('interface.surveys.problems_title')}</p>

            <ul className="ps-4">
                {problems.map((problem, indice) => (
                    <li key={`${problem.key}-${problem.question_position}-${indice}`}>
                        {problem.question_position !== null && (
                            <strong>
                                {t('interface.surveys.problem_at', {
                                    position: problem.question_position,
                                })}{' '}
                            </strong>
                        )}

                        {t(`interface.surveys.problem_${problem.key}`, problem.replacements)}
                    </li>
                ))}
            </ul>
        </div>
    )
}
