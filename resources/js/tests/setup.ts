import '@testing-library/jest-dom/vitest'

import { cleanup } from '@testing-library/react'
import { afterEach, vi } from 'vitest'

/*
 * Se desmonta lo montado despues de cada prueba.
 *
 * Sin esto, los componentes de una prueba siguen en el DOM durante la
 * siguiente: getByText encontraria dos elementos iguales y fallaria con un
 * error que no menciona la causa.
 */
afterEach(() => {
    cleanup()
})

/*
 * crypto.randomUUID no existe en todas las versiones de jsdom.
 *
 * El renderizador lo usa para la clave de idempotencia. Un valor creciente y
 * predecible sirve mejor que uno aleatorio: permite comprobar que cambia
 * entre respuestas.
 */
let contador = 0

if (! globalThis.crypto?.randomUUID) {
    Object.defineProperty(globalThis, 'crypto', {
        value: {
            ...globalThis.crypto,
            randomUUID: () => `00000000-0000-4000-8000-${String(++contador).padStart(12, '0')}`,
        },
    })
}

/*
 * ResizeObserver tampoco existe en jsdom.
 *
 * Lo usa el widget para informar de su altura. Sin este doble, montar
 * cualquier pantalla que lo importe revienta.
 */
globalThis.ResizeObserver = class {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
} as unknown as typeof ResizeObserver

/*
 * usePage de Inertia, con las traducciones reales.
 *
 * useTranslate() las lee de las props compartidas, que en una prueba no
 * existen. Se simulan aqui una sola vez en lugar de en cada archivo: si cada
 * prueba montara su propio doble, acabarian teniendo textos distintos y
 * comparar seria imposible.
 */
vi.mock('@inertiajs/react', async () => {
    const real = await vi.importActual<typeof import('@inertiajs/react')>('@inertiajs/react')

    return {
        ...real,
        usePage: () => ({
            props: {
                translations: {
                    interface: {
                        renderer: {
                            progress: 'Pregunta :current de :total',
                            next: 'Siguiente',
                            back: 'Anterior',
                            finish: 'Terminar',
                            no_questions: 'Esta encuesta no tiene preguntas que mostrar.',
                            characters: ':count de :max caracteres',
                            problem_required: 'Esta pregunta es obligatoria.',
                            problem_min_selections: 'Elige al menos :min.',
                            problem_max_selections: 'No puedes elegir mas de :max.',
                            problem_min_length: 'Escribe al menos :min caracteres.',
                            problem_max_length: 'No puedes escribir mas de :max caracteres.',
                            problem_not_a_number: 'Escribe un numero.',
                            problem_min: 'El minimo es :min.',
                            problem_max: 'El maximo es :max.',
                        },
                    },
                },
            },
        }),
    }
})
