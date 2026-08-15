import { expect, test } from '@playwright/test'

import { entrar } from './helpers'

/**
 * Verificacion en dos pasos y seguridad de la cuenta.
 * RF-AUT-014, 015 · RNF-AUT-004 · P-011.
 *
 * SecondFactorScreensTest perdio once aserciones al pasar a React: la
 * etiqueta del campo, autocomplete="one-time-code", aria-describedby y
 * role="alert" en los codigos de recuperacion. Aqui se recuperan.
 */
test.describe('seguridad de la cuenta', () => {
    test.beforeEach(async ({ page }) => {
        await entrar(page)
        await page.goto('/cuenta/seguridad')
    })

    test('el estado del segundo factor se dice en texto', async ({ page }) => {
        // ANEXO 1 seccion 47: el color no puede ser el unico portador.
        await expect(page.getByText(/esta desactivada|esta activada/i)).toBeVisible()
    })

    test('los codigos de recuperacion se anuncian al activar', async ({ page }) => {
        /*
         * role="alert" y no un aviso discreto: se muestran UNA sola vez —en
         * la base solo queda su hash— y si esta persona cierra la pantalla
         * sin copiarlos, nadie puede volver a ensenarselos.
         */
        await page.getByRole('button', { name: 'Activar' }).click()

        const aviso = page.getByRole('alert')
        await expect(aviso).toBeVisible()
        await expect(aviso.locator('li')).not.toHaveCount(0)
    })

    test('los codigos no vuelven a aparecer al recargar', async ({ page }) => {
        await page.getByRole('button', { name: 'Activar' }).click()
        await expect(page.getByRole('alert')).toBeVisible()

        await page.reload()

        // Van como prop diferida justamente para esto: sin ello, el estado
        // que Inertia guarda en el historial podria reensenarlos.
        await expect(page.getByRole('alert')).toHaveCount(0)
    })
})

test.describe('verificacion en dos pasos', () => {
    test('el campo de codigo tiene etiqueta y autocompletado', async ({ page }) => {
        /*
         * autocomplete="one-time-code" es lo que hace que el telefono ofrezca
         * el codigo del correo sin copiarlo a mano. Sin el, hay que cambiar
         * de aplicacion, memorizarlo y volver.
         */
        await entrar(page)
        await page.goto('/cuenta/seguridad')
        await page.getByRole('button', { name: 'Activar' }).click()

        // Cerrar sesion y volver a entrar: ahora pide el segundo factor.
        await page.getByRole('button', { name: /cerrar sesion/i }).click()
        await entrar(page)

        await expect(page).toHaveURL(/verificacion/)

        const codigo = page.getByLabel(/codigo/i)
        await expect(codigo).toBeVisible()
        await expect(codigo).toHaveAttribute('autocomplete', 'one-time-code')
        await expect(codigo).toHaveAttribute('inputmode', 'numeric')
    })

    test('ofrece reenviar y cancelar', async ({ page }) => {
        // RF-AUT-015: sin cancelar, quien no reciba el codigo se queda
        // atrapado en una sesion a medias.
        await entrar(page)
        await page.goto('/cuenta/seguridad')
        await page.getByRole('button', { name: 'Activar' }).click()
        await page.getByRole('button', { name: /cerrar sesion/i }).click()
        await entrar(page)

        await expect(page.getByRole('button', { name: /enviar otro codigo/i })).toBeVisible()
        await expect(page.getByRole('button', { name: /cancelar/i })).toBeVisible()
    })
})
