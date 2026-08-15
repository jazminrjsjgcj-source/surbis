import { useCallback, useEffect, useRef, useState } from 'react'

import { clearDraft, readDraft, saveDraft } from '@/lib/idb'

/**
 * Persistencia del constructor.
 *
 * Estado local para responder en menos de 200 ms (RNF-AO-BLD-003), guardado
 * automatico tras un segundo sin cambios, respaldo en IndexedDB, y bloqueo
 * optimista con lock_version.
 *
 * Los seis estados son explicitos y excluyentes. Si cada parte del componente
 * dedujera por su cuenta que mostrar, apareceria alguna combinacion que nadie
 * previo: "guardando" y "error" a la vez, o "guardado" con cambios pendientes.
 */
export type SaveState =
    | 'synced'
    | 'pending'
    | 'saving'
    | 'local'
    | 'error'
    | 'conflict'
    /** El servidor rechazo los datos. No se reintenta: no van a mejorar solos. */
    | 'rejected'

interface Conflict {
    actual: number
    version: unknown
}

interface Options<T> {
    versionUlid: string
    initialLock: number
    endpoint: string
    readOnly: boolean
    value: T
}

const DEBOUNCE_MS = 1000
const MAX_BACKOFF_MS = 30_000

export function useBuilderPersistence<T>({
    versionUlid,
    initialLock,
    endpoint,
    readOnly,
    value,
}: Options<T>) {
    const [state, setState] = useState<SaveState>('synced')
    const [conflict, setConflict] = useState<Conflict | null>(null)
    const [rejection, setRejection] = useState<string | null>(null)
    const [lastSavedAt, setLastSavedAt] = useState<number | null>(null)

    const lock = useRef(initialLock)
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null)
    const backoff = useRef(1000)
    const primeraCarga = useRef(true)

    const send = useCallback(async (): Promise<void> => {
        if (readOnly) {
            return
        }

        setState('saving')

        try {
            const response = await fetch(endpoint, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ lock_version: lock.current, questions: value }),
            })

            if (response.status === 409) {
                /*
                 * Conflicto. Es el UNICO estado que detiene el autoguardado.
                 *
                 * Los demas reintentan, porque un fallo de red se resuelve
                 * solo. Este no: hay otra persona editando y hace falta una
                 * decision humana. Seguir reintentando sobrescribiria su
                 * trabajo, que es exactamente lo que el bloqueo evita.
                 *
                 * El respaldo local NO se borra: es lo unico que conserva lo
                 * que esta persona escribio.
                 */
                const datos = await response.json()

                /*
                 * El numero actual del servidor se guarda YA.
                 *
                 * Antes solo se aplicaba si la persona pulsaba "conservar lo
                 * mio": si cerraba el aviso o recargaba a medias, el cliente
                 * seguia con un numero viejo y el siguiente intento volvia a
                 * dar conflicto. Un conflicto que no se puede abandonar deja
                 * la pantalla inutilizable.
                 *
                 * Guardarlo no sobrescribe nada: el autoguardado sigue
                 * detenido hasta que alguien decida.
                 */
                lock.current = datos.actual

                setConflict({ actual: datos.actual, version: datos.version })
                setState('conflict')

                return
            }

            /*
             * 422: el servidor RECHAZA lo que se manda.
             *
             * Es el unico estado, junto al conflicto, que detiene el
             * autoguardado. Reintentar no sirve de nada —los datos no van a
             * mejorar solos— y hacerlo en bucle produjo 208 peticiones en una
             * prueba, cada una moviendo lock_version hasta provocar un
             * conflicto falso contra uno mismo.
             *
             * Y el mensaje se muestra. Antes el indicador solo decia "cambios
             * sin guardar" sin explicar por que no se guardaban.
             */
            if (response.status === 422) {
                const datos = await response.json()
                const primero = Object.values(datos.errors ?? {})[0]

                setRejection(
                    Array.isArray(primero) ? String(primero[0]) : (datos.message ?? '')
                )
                setState('rejected')

                return
            }

            if (!response.ok) {
                throw new Error(`El servidor respondio ${response.status}`)
            }

            const datos = await response.json()
            lock.current = datos.lock_version
            setRejection(null)

            /*
             * El respaldo se borra SOLO cuando el servidor confirma. Un
             * respaldo huerfano al volver a entrar obligaria a decidir si
             * restaurarlo, y restaurar algo viejo sobre algo nuevo destruye
             * trabajo en lugar de salvarlo.
             */
            await clearDraft(versionUlid)

            backoff.current = 1000
            setLastSavedAt(Date.now())
            setState('synced')
        } catch {
            /*
             * Fallo de red o del servidor. El trabajo esta en IndexedDB, asi
             * que el estado es "local" y no "error": lo primero que la persona
             * necesita saber es que no se ha perdido nada.
             *
             * Y se reintenta con espera creciente. Sin reintento, "guardado
             * solo en este equipo" se convierte en una etiqueta permanente
             * que la gente aprende a ignorar.
             */
            setState('local')

            backoff.current = Math.min(backoff.current * 2, MAX_BACKOFF_MS)

            timer.current = setTimeout(() => {
                void send()
            }, backoff.current)
        }
    }, [endpoint, readOnly, value, versionUlid])

    // Cada cambio: respaldo inmediato y guardado tras un segundo de calma.
    useEffect(() => {
        // 'rejected' NO detiene el ciclo: al corregir lo que fallaba hay que
        // poder volver a intentarlo sin recargar. Lo que se evita es el
        // reintento AUTOMATICO, que es distinto.
        if (readOnly || state === 'conflict') {
            return
        }

        if (primeraCarga.current) {
            primeraCarga.current = false

            return
        }

        setState('pending')

        // El respaldo NO espera al debounce: si la pestana se cierra en ese
        // segundo, es lo unico que queda.
        void saveDraft({
            versionUlid,
            lockVersion: lock.current,
            questions: value,
            savedAt: Date.now(),
        })

        if (timer.current) {
            clearTimeout(timer.current)
        }

        timer.current = setTimeout(() => {
            void send()
        }, DEBOUNCE_MS)

        return () => {
            if (timer.current) {
                clearTimeout(timer.current)
            }
        }
    }, [value, readOnly, state, versionUlid, send])

    const saveNow = useCallback((): void => {
        if (timer.current) {
            clearTimeout(timer.current)
        }

        void send()
    }, [send])

    /**
     * Reintentar tras un conflicto.
     *
     * Relee y guarda con el numero nuevo: NO se salta la comprobacion. Si
     * mientras la persona decidia hubo una tercera edicion, esto vuelve a dar
     * 409, y es correcto que lo haga.
     */
    const retryWithServerVersion = useCallback((actual: number): void => {
        lock.current = actual
        setConflict(null)
        void send()
    }, [send])

    const hasLocalBackup = useCallback(
        async (): Promise<boolean> => (await readDraft(versionUlid)) !== null,
        [versionUlid],
    )

    return { state, conflict, rejection, lastSavedAt, saveNow, retryWithServerVersion, hasLocalBackup }
}
