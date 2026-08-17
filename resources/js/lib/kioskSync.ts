import {
    type QueuedResponse,
    markFailed,
    markSynced,
    remove,
    status,
    take,
} from '@/lib/kioskQueue'

/**
 * Envia lo que hay en la cola. Fase 10.
 *
 * Se ejecuta EN SEGUNDO PLANO y no bloquea nada: quien contesta ya recibio
 * su "gracias" cuando la respuesta quedo guardada en el dispositivo. Esto
 * solo mueve lo guardado al servidor.
 */

/** De cuantas en cuantas. Mas grande satura la red de una tableta. */
const BATCH = 10

let enMarcha = false

export interface SyncResult {
    sent: number
    failed: number
    pending: number
}

async function send(url: string, item: QueuedResponse): Promise<boolean> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            idempotency_key: item.idempotencyKey,
            session: item.sessionUlid,
            answers: item.answers,
            comment: item.comment,
        }),
    })

    /*
     * 422 se trata como ENVIADA y se retira de la cola.
     *
     * Significa que el servidor rechaza esos datos, y reintentarlos mil veces
     * no los va a arreglar: quedaria una respuesta atascada bloqueando la
     * cola para siempre. Se pierde una respuesta invalida; la alternativa es
     * perderlas todas.
     */
    return response.ok || response.status === 422
}

/**
 * Vacia la cola hasta donde pueda.
 *
 * El cerrojo `enMarcha` evita dos sincronizaciones a la vez: dos tandas
 * enviando lo mismo no duplicarian —el UUID lo impide— pero gastarian el
 * doble de red en una tableta que quiza tiene poca.
 */
export async function sync(url: string): Promise<SyncResult> {
    if (enMarcha || !navigator.onLine) {
        const estado = await status()

        return { sent: 0, failed: 0, pending: estado.pending }
    }

    enMarcha = true

    let enviadas = 0
    let fallidas = 0

    try {
        // Se va por tandas hasta que no quede nada o algo falle: si la
        // conexion se cayo, seguir intentando las 5000 no ayuda.
        for (;;) {
            const tanda = await take(BATCH)

            if (tanda.length === 0) {
                break
            }

            let algunaFallo = false

            for (const item of tanda) {
                try {
                    if (await send(url, item)) {
                        await remove(item.id)
                        enviadas++

                        continue
                    }

                    await markFailed(item.id, 'server')
                    fallidas++
                    algunaFallo = true
                } catch (error) {
                    await markFailed(item.id, String(error))
                    fallidas++
                    algunaFallo = true
                }
            }

            if (algunaFallo) {
                break
            }
        }

        if (enviadas > 0) {
            await markSynced()
        }
    } finally {
        // En el finally: si algo revienta a mitad, el cerrojo tiene que
        // soltarse igual o la tableta no vuelve a sincronizar nunca.
        enMarcha = false
    }

    const estado = await status()

    return { sent: enviadas, failed: fallidas, pending: estado.pending }
}

/**
 * Sincroniza cuando vuelve la conexion y cada pocos minutos.
 *
 * El evento `online` del navegador no es fiable —a veces dice que hay red
 * cuando no llega a ningun sitio— asi que ademas se intenta por reloj.
 */
export function startSyncing(url: string, everyMs = 120_000): () => void {
    void sync(url)

    const alVolver = (): void => {
        void sync(url)
    }

    window.addEventListener('online', alVolver)

    const reloj = window.setInterval(alVolver, everyMs)

    return () => {
        window.removeEventListener('online', alVolver)
        window.clearInterval(reloj)
    }
}
