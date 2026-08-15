import type { ReactNode } from 'react'

export interface Column<T> {
    key: string
    header: string
    /** Contenido de la celda. Recibe la fila entera. */
    cell: (row: T) => ReactNode
    /** Numeros y codigos, para que las cifras se alineen. */
    numeric?: boolean
}

interface Props<T> {
    caption: string
    columns: Column<T>[]
    rows: T[]
    rowKey: (row: T) => string
}

/**
 * Tabla de datos.
 *
 * Replica la de Blade —.table en app.css— y hereda sus decisiones:
 * <caption> siempre, scope="col" en cada cabecera, y envoltorio con
 * desplazamiento lateral en pantallas estrechas.
 *
 * El caption no es decorativo: sin el, un lector de pantalla anuncia "tabla
 * de 6 columnas" sin decir de que. Y scope="col" es lo que relaciona cada
 * celda con su cabecera; sin eso la tabla se lee como una lista de valores
 * sueltos.
 */
export default function DataTable<T>({ caption, columns, rows, rowKey }: Props<T>) {
    return (
        <div className="card">
            <div className="table-wrap">
                <table className="table">
                    <caption className="ps-3.5 pt-3">{caption}</caption>

                    <thead>
                        <tr>
                            {columns.map((columna) => (
                                <th key={columna.key} scope="col">
                                    {columna.header}
                                </th>
                            ))}
                        </tr>
                    </thead>

                    <tbody>
                        {rows.map((fila) => (
                            <tr key={rowKey(fila)}>
                                {columns.map((columna) => (
                                    <td
                                        key={columna.key}
                                        className={columna.numeric ? 'table-numeric' : undefined}
                                    >
                                        {columna.cell(fila)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    )
}
