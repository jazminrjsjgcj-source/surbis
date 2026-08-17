import { useTranslate } from '@/lib/translate'

export interface Metric {
    available: boolean
    responses: number | null
    average: number | null
    percentage: number | null
    invalidated: number
}

interface Props {
    label: string
    metric: Metric
    kind: 'responses' | 'average' | 'percentage'
}

/**
 * Una tarjeta de indicador. RNF-AO-RES-003.
 *
 * Cuando no se alcanza el umbral dice «Datos insuficientes» y NADA MÁS: ni el
 * valor ni cuántas respuestas hay. Decisión del área usuaria: decir «hay 3»
 * ya es información — con dos días de datos se deduce quién atendía esa
 * ventanilla.
 */
export default function MetricCard({ label, metric, kind }: Props) {
    const t = useTranslate()

    if (!metric.available) {
        return (
            <div className="card card-pad">
                <span className="hint">{label}</span>
                <p className="mt-1 text-lg">{t('interface.analytics.insufficient')}</p>
                <span className="hint">{t('interface.analytics.insufficient_help')}</span>
            </div>
        )
    }

    const valor =
        kind === 'responses'
            ? String(metric.responses ?? 0)
            : kind === 'average'
              ? (metric.average?.toFixed(2) ?? '—')
              : metric.percentage === null
                ? '—'
                : `${metric.percentage}%`

    return (
        <div className="card card-pad">
            <span className="hint">{label}</span>
            <p className="mt-1 text-2xl font-semibold">{valor}</p>

            {/*
                Las invalidadas se dicen siempre que las haya: un número que
                baja sin explicación genera desconfianza.
            */}
            {metric.invalidated > 0 && (
                <span className="hint">
                    {t('interface.analytics.excluded', { count: metric.invalidated })}
                </span>
            )}
        </div>
    )
}
