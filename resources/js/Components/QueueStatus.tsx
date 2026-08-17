import { useEffect, useState } from 'react'

import { type QueueStatus as Estado, status } from '@/lib/kioskQueue'
import { useTranslate } from '@/lib/translate'

interface Props {
    limitDays: number
    limitCount: number

    /** Se avisa cuando la cola llega a esta proporción del límite. */
    warnAt: number

    onBlocked: (bloqueado: boolean) => void
}

/**
 * El estado de la cola. Solo lo ve el COLABORADOR, en la preparación.
 *
 * Decisión del área usuaria: quien contesta no ve nada de esto. Un ciudadano
 * no puede hacer nada con "sin conexión", y verlo le haría dudar de si su
 * respuesta contó.
 */
export default function QueueStatus({ limitDays, limitCount, warnAt, onBlocked }: Props) {
    const t = useTranslate()
    const [cola, setCola] = useState<Estado | null>(null)

    useEffect(() => {
        let vivo = true

        async function leer(): Promise<void> {
            try {
                const actual = await status()

                if (vivo) {
                    setCola(actual)
                }
            } catch {
                // Si IndexedDB no responde, la pantalla no dice nada en vez
                // de romperse: el colaborador tiene que poder abrir turno.
            }
        }

        void leer()

        const reloj = window.setInterval(leer, 30_000)

        return () => {
            vivo = false
            window.clearInterval(reloj)
        }
    }, [])

    const diasMasAntigua =
        cola?.oldestAt == null
            ? 0
            : Math.floor((Date.now() - cola.oldestAt) / (1000 * 60 * 60 * 24))

    const ocupacion = Math.max(
        (cola?.pending ?? 0) / limitCount,
        diasMasAntigua / limitDays,
    )

    const estado = ocupacion >= 1 ? 'blocked' : ocupacion >= warnAt ? 'warning' : 'ok'

    useEffect(() => {
        onBlocked(estado === 'blocked')
    }, [estado, onBlocked])

    // Sin nada pendiente no hace falta decir nada: una tableta que funciona
    // no necesita informar de que funciona.
    if (cola === null || (cola.pending === 0 && estado === 'ok')) {
        return null
    }

    return (
        <div
            className={`alert ${estado === 'ok' ? 'alert-neutral' : 'alert-negative'} mt-4`}
            role="status"
        >
            <p className="alert-title">{t(`interface.queue.${estado}`)}</p>

            {/*
                Con números, no con un "hay problemas". El colaborador tiene
                que poder decidir si sigue o llama a alguien, y para eso hace
                falta saber cuántas hay y desde cuándo.
            */}
            <ul>
                <li>{t('interface.queue.pending', { count: cola.pending })}</li>

                {cola.oldestAt !== null && (
                    <li>{t('interface.queue.oldest', { days: diasMasAntigua })}</li>
                )}

                <li>
                    {cola.lastSyncAt === null
                        ? t('interface.queue.never_synced')
                        : t('interface.queue.last_sync', {
                              time: new Date(cola.lastSyncAt).toLocaleString(),
                          })}
                </li>

                <li>
                    {t('interface.queue.capacity', {
                        percent: Math.max(0, Math.round((1 - ocupacion) * 100)),
                    })}
                </li>
            </ul>
        </div>
    )
}
