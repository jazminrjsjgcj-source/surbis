import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts'

export interface ChartPoint {
    day: string
    average: number | null
    percentage: number | null
}

interface Props {
    points: ChartPoint[]
}

/**
 * La serie temporal.
 *
 * En su propio archivo para poder cargarlo con lazy(): recharts pesa unos
 * 400 KB, y solo hace falta en esta pantalla. Dejarlo en el paquete común
 * haría que el resto del panel —sucursales, personas, encuestas— lo
 * descargara sin usarlo nunca.
 */
export default function MetricsChart({ points }: Props) {
    return (
        <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
                <LineChart data={points}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="day" />
                    <YAxis />
                    <Tooltip />

                    {/*
                        connectNulls desactivado: unir dos puntos separados
                        por días ocultos dibujaría una tendencia que no
                        existe.
                    */}
                    <Line
                        type="monotone"
                        dataKey="percentage"
                        stroke="var(--color-primary)"
                        connectNulls={false}
                    />
                </LineChart>
            </ResponsiveContainer>
        </div>
    )
}
