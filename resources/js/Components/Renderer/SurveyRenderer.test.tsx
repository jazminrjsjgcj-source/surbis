import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

import SurveyRenderer from '@/Components/Renderer/SurveyRenderer'
import type { Answers, RenderableSurvey, RenderQuestion } from '@/lib/renderer'

/**
 * El renderizador, con clics de verdad.
 *
 * Es la pantalla que usa gente sin formacion, de pie y con prisa, y hasta
 * ahora su comportamiento solo se habia comprobado a mano.
 *
 * LO QUE ESTAS PRUEBAS NO VEN: jsdom no aplica CSS. Los 44 px de los
 * botones, el contraste y si algo tapa a otra cosa siguen necesitando un
 * navegador de verdad.
 */

function pregunta(overrides: Partial<RenderQuestion> = {}): RenderQuestion {
    return {
        ulid: 'Q1',
        type: 'single_choice',
        text: '¿Como te atendieron?',
        help: null,
        isRequired: false,
        limits: {},
        options: [
            { ulid: 'O1', label: 'Bien', display: 'text', image: null },
            { ulid: 'O2', label: 'Mal', display: 'text', image: null },
        ],
        condition: null,
        ...overrides,
    }
}

function encuesta(overrides: Partial<RenderableSurvey> = {}): RenderableSurvey {
    return {
        name: 'Satisfaccion',
        layout: 'stepped',
        introduction: null,
        thankYou: null,
        allowBack: true,
        commentMode: 'disabled',
        identityMode: 'anonymous',
        inactivitySeconds: 60,
        questions: [pregunta()],
        ...overrides,
    }
}

describe('SurveyRenderer', () => {
    it('muestra la primera pregunta y sus opciones', () => {
        render(<SurveyRenderer survey={encuesta()} onComplete={vi.fn()} />)

        expect(screen.getByText('¿Como te atendieron?')).toBeInTheDocument()
        expect(screen.getByRole('button', { name: 'Bien' })).toBeInTheDocument()
    })

    it('marca la opcion elegida en el MARCADO, no solo con color', async () => {
        /*
         * RNF-COL-007 prohibe que el color sea el unico portador: quien no
         * distingue el vino del gris tiene que poder saber cual eligio.
         */
        const usuario = userEvent.setup()

        render(<SurveyRenderer survey={encuesta()} onComplete={vi.fn()} />)

        const boton = screen.getByRole('button', { name: 'Bien' })

        expect(boton).toHaveAttribute('aria-pressed', 'false')

        await usuario.click(boton)

        expect(boton).toHaveAttribute('aria-pressed', 'true')
    })

    it('avanza de una pregunta a la siguiente', async () => {
        const usuario = userEvent.setup()

        render(
            <SurveyRenderer
                survey={encuesta({
                    questions: [
                        pregunta(),
                        pregunta({ ulid: 'Q2', text: '¿Algo mas?', type: 'long_text', options: [] }),
                    ],
                })}
                onComplete={vi.fn()}
            />,
        )

        expect(screen.getByText('Pregunta 1 de 2')).toBeInTheDocument()

        await usuario.click(screen.getByRole('button', { name: 'Bien' }))
        await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))

        expect(screen.getByText('¿Algo mas?')).toBeInTheDocument()
        expect(screen.getByText('Pregunta 2 de 2')).toBeInTheDocument()
    })

    it('no deja avanzar sin contestar una obligatoria', async () => {
        // RF-COL-016. El servidor lo valida otra vez, pero esto evita que
        // alguien llegue al final sabiendo ya que va a fallar.
        const usuario = userEvent.setup()

        render(
            <SurveyRenderer
                survey={encuesta({
                    questions: [
                        pregunta({ isRequired: true }),
                        pregunta({ ulid: 'Q2', text: 'La segunda' }),
                    ],
                })}
                onComplete={vi.fn()}
            />,
        )

        await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))

        expect(screen.getByRole('alert')).toHaveTextContent('Esta pregunta es obligatoria.')
        expect(screen.queryByText('La segunda')).not.toBeInTheDocument()
    })

    it('retrocede cuando la configuracion lo permite', async () => {
        const usuario = userEvent.setup()

        render(
            <SurveyRenderer
                survey={encuesta({
                    questions: [pregunta(), pregunta({ ulid: 'Q2', text: 'La segunda' })],
                })}
                onComplete={vi.fn()}
            />,
        )

        await usuario.click(screen.getByRole('button', { name: 'Bien' }))
        await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))
        await usuario.click(screen.getByRole('button', { name: 'Anterior' }))

        expect(screen.getByText('¿Como te atendieron?')).toBeInTheDocument()
    })

    it('oculta el boton de retroceder si la encuesta no lo permite', async () => {
        const usuario = userEvent.setup()

        render(
            <SurveyRenderer
                survey={encuesta({
                    allowBack: false,
                    questions: [pregunta(), pregunta({ ulid: 'Q2', text: 'La segunda' })],
                })}
                onComplete={vi.fn()}
            />,
        )

        await usuario.click(screen.getByRole('button', { name: 'Bien' }))
        await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))

        expect(screen.queryByRole('button', { name: 'Anterior' })).not.toBeInTheDocument()
    })

    it('avisa al terminar con las respuestas dadas', async () => {
        const usuario = userEvent.setup()
        const alTerminar = vi.fn()

        render(<SurveyRenderer survey={encuesta()} onComplete={alTerminar} />)

        await usuario.click(screen.getByRole('button', { name: 'Bien' }))
        await usuario.click(screen.getByRole('button', { name: 'Terminar' }))

        expect(alTerminar).toHaveBeenCalledWith({ Q1: 'O1' })
    })

    describe('logica condicional', () => {
        const conSeguimiento = encuesta({
            questions: [
                pregunta(),
                pregunta({
                    ulid: 'Q2',
                    text: '¿Que paso?',
                    type: 'long_text',
                    options: [],
                    condition: { dependsOn: 'Q1', option: 'O2' },
                }),
            ],
        })

        it('no cuenta la pregunta oculta en el progreso', () => {
            /*
             * Decir "Pregunta 1 de 2" cuando la segunda no se va a mostrar
             * haria esperar algo que nunca llega.
             */
            render(<SurveyRenderer survey={conSeguimiento} onComplete={vi.fn()} />)

            expect(screen.getByText('Pregunta 1 de 1')).toBeInTheDocument()
        })

        it('muestra la pregunta cuando se cumple la condicion', async () => {
            const usuario = userEvent.setup()

            render(<SurveyRenderer survey={conSeguimiento} onComplete={vi.fn()} />)

            await usuario.click(screen.getByRole('button', { name: 'Mal' }))

            expect(screen.getByText('Pregunta 1 de 2')).toBeInTheDocument()
        })

        it('retira la respuesta de una pregunta que deja de mostrarse', async () => {
            /*
             * Si alguien contesta "mal", escribe el seguimiento y luego
             * cambia a "bien", esa contestacion ya no tiene sentido: seria
             * una respuesta a una pregunta que no se le hizo.
             */
            const usuario = userEvent.setup()
            const alTerminar = vi.fn()

            render(<SurveyRenderer survey={conSeguimiento} onComplete={alTerminar} />)

            await usuario.click(screen.getByRole('button', { name: 'Mal' }))
            await usuario.click(screen.getByRole('button', { name: 'Siguiente' }))
            await usuario.type(screen.getByRole('textbox'), 'Me trataron fatal')
            await usuario.click(screen.getByRole('button', { name: 'Anterior' }))
            await usuario.click(screen.getByRole('button', { name: 'Bien' }))
            await usuario.click(screen.getByRole('button', { name: 'Terminar' }))

            const respuestas = alTerminar.mock.calls[0][0] as Answers

            expect(respuestas).toEqual({ Q1: 'O1' })
            expect(respuestas.Q2).toBeUndefined()
        })
    })

    describe('modo de lista completa', () => {
        it('muestra todas las preguntas a la vez', () => {
            render(
                <SurveyRenderer
                    survey={encuesta({
                        layout: 'full',
                        questions: [pregunta(), pregunta({ ulid: 'Q2', text: 'La segunda' })],
                    })}
                    onComplete={vi.fn()}
                />,
            )

            expect(screen.getByText('¿Como te atendieron?')).toBeInTheDocument()
            expect(screen.getByText('La segunda')).toBeInTheDocument()
            expect(screen.queryByText(/Pregunta 1 de/)).not.toBeInTheDocument()
        })

        it('no envia si falta una obligatoria', async () => {
            const usuario = userEvent.setup()
            const alTerminar = vi.fn()

            render(
                <SurveyRenderer
                    survey={encuesta({ layout: 'full', questions: [pregunta({ isRequired: true })] })}
                    onComplete={alTerminar}
                />,
            )

            await usuario.click(screen.getByRole('button', { name: 'Terminar' }))

            expect(alTerminar).not.toHaveBeenCalled()
            expect(screen.getByRole('alert')).toBeInTheDocument()
        })
    })

    describe('seleccion multiple', () => {
        const multiple = encuesta({
            questions: [
                pregunta({
                    type: 'multiple_choice',
                    limits: { min_selections: 2 },
                }),
            ],
        })

        it('permite marcar varias', async () => {
            const usuario = userEvent.setup()
            const alTerminar = vi.fn()

            render(<SurveyRenderer survey={multiple} onComplete={alTerminar} />)

            await usuario.click(screen.getByRole('button', { name: 'Bien' }))
            await usuario.click(screen.getByRole('button', { name: 'Mal' }))
            await usuario.click(screen.getByRole('button', { name: 'Terminar' }))

            expect(alTerminar).toHaveBeenCalledWith({ Q1: ['O1', 'O2'] })
        })

        it('respeta el minimo de selecciones', async () => {
            const usuario = userEvent.setup()

            render(<SurveyRenderer survey={multiple} onComplete={vi.fn()} />)

            await usuario.click(screen.getByRole('button', { name: 'Bien' }))
            await usuario.click(screen.getByRole('button', { name: 'Terminar' }))

            expect(screen.getByRole('alert')).toHaveTextContent('Elige al menos 2.')
        })
    })
})
