import { useCallback, useEffect, useRef, useState } from 'react'

export type SaveState = 'clean' | 'dirty' | 'saving' | 'saved' | 'conflict' | 'rejected'

export interface Conflict {
    actual: number
    version: number
}

interface Options<T> {
    url: string
    value: T
    initialLock: number
    readOnly: boolean
}

/**
 * Guardado manual del borrador.
 *
 * SUSTITUYE al autoguardado. Decision del area usuaria, 17 ago 2026, despues
 * de que el autoguardado causara: conflictos contra uno mismo, bucles de 422
 * al escribir, y 118 peticiones seguidas por un ciclo de renderizado.
 *
 * Lo que se conserva del anterior, porque resolvia problemas reales:
 *
 *   el bloqueo optimista   dos personas editando la misma encuesta es un
 *                          caso real, y sobrescribir el trabajo ajeno en
 *                          silencio es peor que un aviso molesto
 *
 *   el aviso al salir      escribir veinte preguntas y perderlas por cerrar
 *                          una pestaña es exactamente lo que el autoguardado
 *                          protegia. Eso si valia la pena
 *
 * Lo que se va: debounce, reintentos con espera creciente, seis estados,
 * cerrojo de peticiones simultaneas y respaldo en IndexedDB. Todo eso existia
 * para que guardar solo funcionara bien, y guardar solo era el problema.
 */
export function useDraftSaving<T>({ url, value, initialLock, readOnly }: Options<T>) {
    const [state, setState] = useState<SaveState>('clean')
    const [conflict, setConflict] = useState<Conflict | null>(null)
    const [rejection, setRejection] = useState<string | null>(null)
    const [lastSavedAt, setLastSavedAt] = useState<number | null>(null)

    const lock = useRef(initialLock)

    /*
     * Lo ultimo guardado, para saber si hay cambios.
     *
     * Se compara por JSON y no por identidad: React crea objetos nuevos en
     * cada render aunque el contenido sea el mismo, y comparar referencias
     * marcaria todo como cambiado siempre. Ese error es el que produjo las
     * 118 peticiones.
     */
    const saved = useRef(JSON.stringify(value))

    const dirty = JSON.stringify(value) !== saved.current

    useEffect(() => {
        if (readOnly || state === 'conflict') {
            return
        }

        setState(dirty ? 'dirty' : state === 'saved' ? 'saved' : 'clean')
    }, [dirty, readOnly, state])

    /*
     * Avisar antes de cerrar con cambios sin guardar.
     *
     * Es lo unico que el autoguardado protegia de verdad: escribir veinte
     * preguntas y perderlas por un clic. El navegador enseña su propio
     * mensaje —no se puede personalizar— pero pregunta, que es lo que hace
     * falta.
     */
    useEffect(() => {
        if (! dirty || readOnly) {
            return
        }

        function avisar(event: BeforeUnloadEvent): void {
            event.preventDefault()
            event.returnValue = ''
        }

        window.addEventListener('beforeunload', avisar)

        return () => window.removeEventListener('beforeunload', avisar)
    }, [dirty, readOnly])

    const save = useCallback(
        async (overrideLock?: number): Promise<void> => {
            if (readOnly) {
                return
            }

            setState('saving')

            try {
                const response = await fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        lock_version: overrideLock ?? lock.current,
                        questions: value,
                    }),
                })

                if (response.status === 409) {
                    const datos = await response.json()

                    /*
                     * El numero del servidor se guarda YA, no solo si alguien
                     * pulsa "conservar lo mio". Sin esto, cerrar el aviso
                     * dejaba la pantalla inutilizable con un numero viejo.
                     */
                    lock.current = datos.actual

                    setConflict({ actual: datos.actual, version: datos.version })
                    setState('conflict')

                    return
                }

                if (response.status === 422) {
                    const datos = await response.json()
                    const primero = Object.values(datos.errors ?? {})[0]

                    setRejection(
                        Array.isArray(primero) ? String(primero[0]) : (datos.message ?? ''),
                    )
                    setState('rejected')

                    return
                }

                if (! response.ok) {
                    throw new Error(`El servidor respondio ${response.status}`)
                }

                const datos = await response.json()

                lock.current = datos.lock_version
                saved.current = JSON.stringify(value)

                setConflict(null)
                setRejection(null)
                setLastSavedAt(Date.now())
                setState('saved')
            } catch {
                setRejection(null)
                setState('rejected')
            }
        },
        [readOnly, url, value],
    )

    /** Releer con el numero del servidor y volver a intentar. */
    const retryWithServerVersion = useCallback(async (): Promise<void> => {
        if (conflict === null) {
            return
        }

        setConflict(null)
        await save(conflict.actual)
    }, [conflict, save])

    return { state, dirty, conflict, rejection, lastSavedAt, save, retryWithServerVersion }
}
