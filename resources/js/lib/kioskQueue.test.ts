import 'fake-indexeddb/auto'

import { beforeEach, describe, expect, it } from 'vitest'

import { enqueue, markFailed, markSynced, remove, status, take } from '@/lib/kioskQueue'

/**
 * La cola local del quiosco.
 *
 * Es lo que sostiene el "gracias" que ve el ciudadano: la respuesta se guarda
 * PRIMERO aqui, y solo entonces se le da las gracias. Hasta ahora no tenia
 * ninguna prueba porque IndexedDB no existe en PHPUnit.
 *
 * fake-indexeddb la implementa en memoria, con el mismo comportamiento
 * asincrono y transaccional que el navegador.
 */

function respuesta(clave: string) {
    return {
        idempotencyKey: clave,
        sessionUlid: 'SESION1',
        surveyVersionId: 'VERSION1',
        answers: { Q1: 'O1' },
        comment: null,
    }
}

describe('kioskQueue', () => {
    beforeEach(async () => {
        // Cada prueba empieza con la cola vacia: fake-indexeddb conserva los
        // datos entre pruebas del mismo archivo.
        for (const item of await take(1000)) {
            await remove(item.id)
        }
    })

    it('guarda una respuesta y la cuenta como pendiente', async () => {
        await enqueue(respuesta('A'))

        const estado = await status()

        expect(estado.pending).toBe(1)
        expect(estado.oldestAt).not.toBeNull()
    })

    it('la escritura se resuelve solo cuando esta confirmada', async () => {
        /*
         * LO QUE DA SENTIDO A LA COLA.
         *
         * enqueue espera al COMMIT de la transaccion, no al put. Un put
         * resuelto todavia puede abortar, y decir "gracias" ahi confirmaria
         * una respuesta que puede perderse.
         */
        await enqueue(respuesta('B'))

        // Si enqueue hubiera resuelto antes de tiempo, esta lectura
        // inmediata no encontraria nada.
        const pendientes = await take(10)

        expect(pendientes).toHaveLength(1)
        expect(pendientes[0].idempotencyKey).toBe('B')
    })

    it('devuelve las pendientes de la mas antigua a la mas nueva', async () => {
        // Se envian en orden: la que lleva mas esperando sale primero.
        await enqueue(respuesta('primera'))
        await new Promise((r) => setTimeout(r, 5))
        await enqueue(respuesta('segunda'))

        const pendientes = await take(10)

        expect(pendientes.map((p) => p.idempotencyKey)).toEqual(['primera', 'segunda'])
    })

    it('la misma clave no crea dos entradas', async () => {
        /*
         * El id de la entrada ES la clave de idempotencia. Un reintento con
         * la misma clave sobrescribe en vez de duplicar.
         */
        await enqueue(respuesta('repetida'))
        await enqueue(respuesta('repetida'))

        expect((await status()).pending).toBe(1)
    })

    it('un fallo cuenta el intento pero NO descarta la respuesta', async () => {
        /*
         * Decision del area usuaria: las respuestas guardadas nunca se
         * eliminan automaticamente. Una que no entra puede ser un problema
         * del servidor, y tirarla seria perder lo que alguien contesto.
         */
        await enqueue(respuesta('C'))

        await markFailed('C', 'sin conexion')
        await markFailed('C', 'sin conexion')

        const pendientes = await take(10)

        expect(pendientes).toHaveLength(1)
        expect(pendientes[0].attempts).toBe(2)
        expect(pendientes[0].lastError).toBe('sin conexion')
    })

    it('al enviarse se retira de la cola', async () => {
        await enqueue(respuesta('D'))
        await remove('D')

        expect((await status()).pending).toBe(0)
    })

    it('recuerda cuando se sincronizo por ultima vez', async () => {
        // El colaborador lo ve en la pantalla de preparacion: sin esa fecha,
        // no puede saber si la tableta lleva un dia o una semana sin enviar.
        expect((await status()).lastSyncAt).toBeNull()

        await markSynced()

        expect((await status()).lastSyncAt).not.toBeNull()
    })

    it('aguanta muchas respuestas sin perder ninguna', async () => {
        /*
         * El limite es 5000. Con cien se comprueba que el indice por fecha
         * funciona y que nada se pisa: si el id no fuera unico por respuesta,
         * aqui quedaria una sola.
         */
        for (let i = 0; i < 100; i++) {
            await enqueue(respuesta(`masiva-${i}`))
        }

        expect((await status()).pending).toBe(100)
    })
})
