/**
 * Respaldo local del borrador.
 *
 * Existe para el unico caso que el guardado automatico no cubre: cerrar la
 * pestana con una peticion en vuelo, o perder la red justo despues de
 * escribir. Sin esto, ese trabajo se pierde sin que nadie lo sepa.
 *
 * IndexedDB y no localStorage: localStorage es sincrono y bloquea el hilo de
 * la interfaz, que es lo contrario de lo que pide RNF-AO-BLD-003.
 *
 * Es ademas el primer contacto con IndexedDB del proyecto, y eso es
 * deliberado: la base del quiosco offline es la misma (Fase 10). Aprenderlo
 * aqui, donde equivocarse no pierde respuestas de ciudadanos, es mejor sitio.
 */

const DB_NAME = 'encuestas-builder'
const STORE = 'drafts'
const VERSION = 1

interface StoredDraft {
    versionUlid: string
    lockVersion: number
    questions: unknown
    savedAt: number
}

function open(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, VERSION)

        request.onupgradeneeded = () => {
            const db = request.result

            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'versionUlid' })
            }
        }

        request.onsuccess = () => resolve(request.result)
        request.onerror = () => reject(request.error)
    })
}

export async function saveDraft(draft: StoredDraft): Promise<void> {
    const db = await open()

    await new Promise<void>((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite')
        tx.objectStore(STORE).put(draft)
        tx.oncomplete = () => resolve()
        tx.onerror = () => reject(tx.error)
    })

    db.close()
}

export async function readDraft(versionUlid: string): Promise<StoredDraft | null> {
    const db = await open()

    const draft = await new Promise<StoredDraft | null>((resolve, reject) => {
        const request = db.transaction(STORE, 'readonly').objectStore(STORE).get(versionUlid)
        request.onsuccess = () => resolve((request.result as StoredDraft) ?? null)
        request.onerror = () => reject(request.error)
    })

    db.close()

    return draft
}

/**
 * Se borra en cuanto el servidor confirma.
 *
 * Un respaldo huerfano de hace tres dias es peor que ninguno: al volver a
 * entrar habria que decidir si restaurarlo, y restaurar algo viejo sobre algo
 * nuevo destruye trabajo en lugar de salvarlo.
 */
export async function clearDraft(versionUlid: string): Promise<void> {
    const db = await open()

    await new Promise<void>((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite')
        tx.objectStore(STORE).delete(versionUlid)
        tx.oncomplete = () => resolve()
        tx.onerror = () => reject(tx.error)
    })

    db.close()
}

export type { StoredDraft }
