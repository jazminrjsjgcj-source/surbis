import type { Answers } from '@/lib/renderer'

/**
 * La cola local del quiosco. RNF-COL-014 · Fase 10.
 *
 * IndexedDB y no localStorage: localStorage es sincrono —bloquea la pantalla
 * mientras escribe— tiene un limite de unos 5 MB, y guarda solo cadenas. Con
 * cinco mil respuestas pendientes ninguna de las tres cosas sirve.
 */

export interface QueuedResponse {
    id: string
    idempotencyKey: string
    sessionUlid: string
    surveyVersionId: string
    answers: Answers
    comment: string | null
    queuedAt: number
    attempts: number
    lastError: string | null
}

export interface QueueStatus {
    pending: number
    oldestAt: number | null
    lastSyncAt: number | null
}

const DB_NAME = 'kiosk'
const DB_VERSION = 1
const STORE = 'pending'
const META = 'meta'

function open(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION)

        request.onupgradeneeded = () => {
            const db = request.result

            if (!db.objectStoreNames.contains(STORE)) {
                const store = db.createObjectStore(STORE, { keyPath: 'id' })

                // Por fecha: la mas antigua decide si se alcanzo el limite de
                // dias, y se envia primero.
                store.createIndex('queuedAt', 'queuedAt')
            }

            if (!db.objectStoreNames.contains(META)) {
                db.createObjectStore(META, { keyPath: 'key' })
            }
        }

        request.onsuccess = () => resolve(request.result)
        request.onerror = () => reject(request.error)
    })
}

function promisify<T>(request: IDBRequest<T>): Promise<T> {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result)
        request.onerror = () => reject(request.error)
    })
}

/**
 * Guarda una respuesta. LANZA si no puede.
 *
 * Que lance es el punto: quien llama tiene que saber si de verdad quedo
 * guardada antes de decir "gracias". Decision del area usuaria: nunca se
 * confirma al ciudadano una respuesta que no quedo guardada.
 */
export async function enqueue(item: Omit<QueuedResponse, 'id' | 'queuedAt' | 'attempts' | 'lastError'>): Promise<void> {
    const db = await open()

    const registro: QueuedResponse = {
        ...item,
        id: item.idempotencyKey,
        queuedAt: Date.now(),
        attempts: 0,
        lastError: null,
    }

    const tx = db.transaction(STORE, 'readwrite')

    await promisify(tx.objectStore(STORE).put(registro))

    /*
     * Se espera al COMMIT de la transaccion, no solo al put.
     *
     * Un put resuelto no significa escrito en disco: la transaccion puede
     * abortar despues. Decir "gracias" ahi seria confirmar algo que todavia
     * puede perderse.
     */
    await new Promise<void>((resolve, reject) => {
        tx.oncomplete = () => resolve()
        tx.onerror = () => reject(tx.error)
        tx.onabort = () => reject(tx.error)
    })
}

export async function status(): Promise<QueueStatus> {
    const db = await open()
    const tx = db.transaction([STORE, META], 'readonly')
    const store = tx.objectStore(STORE)

    const pending = await promisify(store.count())

    // La mas antigua, con el indice: recorrer todas para encontrarla seria
    // caro con cinco mil.
    const cursor = await promisify(store.index('queuedAt').openCursor())
    const oldestAt = cursor?.value?.queuedAt ?? null

    const meta = await promisify(tx.objectStore(META).get('lastSync'))

    return {
        pending,
        oldestAt,
        lastSyncAt: (meta as { value?: number } | undefined)?.value ?? null,
    }
}

/** Las pendientes, de la mas antigua a la mas nueva. */
export async function take(limit: number): Promise<QueuedResponse[]> {
    const db = await open()
    const tx = db.transaction(STORE, 'readonly')

    const todas = await promisify(tx.objectStore(STORE).index('queuedAt').getAll())

    return (todas as QueuedResponse[]).slice(0, limit)
}

export async function remove(id: string): Promise<void> {
    const db = await open()
    const tx = db.transaction(STORE, 'readwrite')

    await promisify(tx.objectStore(STORE).delete(id))
}

/**
 * Marca un intento fallido.
 *
 * Se cuenta pero NO se descarta nunca por muchos intentos: una respuesta que
 * no entra puede ser un problema del servidor, y tirarla seria perder lo que
 * alguien contesto. Decision del area usuaria: las respuestas guardadas nunca
 * se eliminan automaticamente.
 */
export async function markFailed(id: string, error: string): Promise<void> {
    const db = await open()
    const tx = db.transaction(STORE, 'readwrite')
    const store = tx.objectStore(STORE)

    const actual = await promisify(store.get(id)) as QueuedResponse | undefined

    if (actual === undefined) {
        return
    }

    await promisify(store.put({
        ...actual,
        attempts: actual.attempts + 1,
        lastError: error,
    }))
}

export async function markSynced(): Promise<void> {
    const db = await open()
    const tx = db.transaction(META, 'readwrite')

    await promisify(tx.objectStore(META).put({ key: 'lastSync', value: Date.now() }))
}
