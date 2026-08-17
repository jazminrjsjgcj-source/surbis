import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

import SaveDraftButton from '@/Components/SaveDraftButton'

/**
 * El boton de guardar el borrador.
 *
 * ESTAS PRUEBAS NACEN DE UN FALLO REAL que estuvo activo desde que se retiro
 * el autoguardado: el constructor NO guardaba nada.
 *
 * La causa era una linea:
 *
 *     onSave={save}      en lugar de     onSave={() => save()}
 *
 * El onClick de React pasa el EVENTO como primer argumento, y save() espera
 * ahi un lock_version opcional. Asi que lock_version se convertia en un
 * objeto de evento, JSON.stringify lanzaba por referencias circulares, y el
 * catch lo tomaba por un fallo del servidor.
 *
 * El sintoma no se parecia a la causa: aparecia "No se pudo guardar" SIN
 * ninguna peticion en Network. Costo diez intentos encontrarlo mirando el
 * servidor, el bloqueo optimista y el estado del formulario.
 */
describe('SaveDraftButton', () => {
    it('llama a onSave SIN argumentos', async () => {
        /*
         * LA PRUEBA QUE HABRIA AHORRADO LA BUSQUEDA.
         *
         * toHaveBeenCalled() a secas habria pasado con el fallo dentro: la
         * funcion SI se llamaba. Lo que importaba era CON QUE.
         */
        const usuario = userEvent.setup()
        const alGuardar = vi.fn()

        render(
            <SaveDraftButton
                state="dirty"
                dirty={true}
                rejection={null}
                lastSavedAt={null}
                onSave={alGuardar}
            />,
        )

        await usuario.click(screen.getByRole('button'))

        expect(alGuardar).toHaveBeenCalledTimes(1)
        expect(alGuardar).toHaveBeenCalledWith()
    })

    it('se puede pulsar cuando hay cambios', async () => {
        const usuario = userEvent.setup()
        const alGuardar = vi.fn()

        render(
            <SaveDraftButton
                state="dirty"
                dirty={true}
                rejection={null}
                lastSavedAt={null}
                onSave={alGuardar}
            />,
        )

        expect(screen.getByRole('button')).toBeEnabled()

        await usuario.click(screen.getByRole('button'))

        expect(alGuardar).toHaveBeenCalled()
    })

    it('no se puede pulsar sin cambios', () => {
        // Ofrecerlo invitaria a pulsar por si acaso.
        render(
            <SaveDraftButton
                state="saved"
                dirty={false}
                rejection={null}
                lastSavedAt={Date.now()}
                onSave={vi.fn()}
            />,
        )

        expect(screen.getByRole('button')).toBeDisabled()
    })

    it('SI se puede reintentar tras un rechazo, aunque no haya cambios nuevos', () => {
        /*
         * Un fallo de red no cambia lo escrito, asi que dirty sigue como
         * estaba. Sin esta excepcion, un rechazo dejaria el boton apagado y
         * el trabajo atrapado en la pantalla.
         */
        render(
            <SaveDraftButton
                state="rejected"
                dirty={false}
                rejection="No se pudo guardar."
                lastSavedAt={null}
                onSave={vi.fn()}
            />,
        )

        expect(screen.getByRole('button')).toBeEnabled()
    })

    it('no se puede pulsar mientras guarda', () => {
        // Dos peticiones a la vez con el mismo lock_version harian que la
        // segunda diera conflicto contra la primera.
        render(
            <SaveDraftButton
                state="saving"
                dirty={true}
                rejection={null}
                lastSavedAt={null}
                onSave={vi.fn()}
            />,
        )

        expect(screen.getByRole('button')).toBeDisabled()
    })

    it('anuncia el motivo del rechazo, no un mensaje generico', () => {
        /*
         * Cuando el servidor explica que paso, se dice eso. "No se pudo
         * guardar" solo cuando de verdad no hay nada mejor que decir.
         */
        render(
            <SaveDraftButton
                state="rejected"
                dirty={false}
                rejection="Dos opciones tienen el mismo valor."
                lastSavedAt={null}
                onSave={vi.fn()}
            />,
        )

        expect(screen.getByRole('status')).toHaveTextContent(
            'Dos opciones tienen el mismo valor.',
        )
    })

    it('el estado se anuncia a un lector de pantalla', () => {
        // role="status": quien no ve el boton tambien tiene que enterarse de
        // que se guardo.
        render(
            <SaveDraftButton
                state="saved"
                dirty={false}
                rejection={null}
                lastSavedAt={Date.now()}
                onSave={vi.fn()}
            />,
        )

        expect(screen.getByRole('status')).toBeInTheDocument()
    })
})
