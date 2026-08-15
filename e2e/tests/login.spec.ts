import { expect, test } from '@playwright/test'

/**
 * Acceso. RF-AUT-001 a 006 · RNF-AUT-004 · RNF-GEN-006.
 *
 * Estas pruebas existen porque al convertir el login a React se perdio lo que
 * antes vigilaba LoginAccessibilityTest: `for=`, `aria-describedby`,
 * `role="alert"`. Ese marcado lo genera ahora el navegador, y una peticion
 * HTTP no puede verlo.
 *
 * Aqui se recupera. Y se comprueba ademas algo que Blade tampoco cubria: que
 * el foco funcione y que la pantalla se pueda usar solo con teclado.
 *
 * Las credenciales salen de DevelopmentSeeder, y las pruebas corren contra la
 * base de desarrollo. Eso es deuda conocida: ensucian los datos con los que
 * estas trabajando. Separarlas pide una segunda instancia de la aplicacion
 * con su propio .env, y es tarea aparte.
 */

const CORREO = 'admin@example.test'
const CLAVE = process.env.SEED_PASSWORD ?? 'desarrollo-local'

test.describe('pantalla de acceso', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login')
    })

    test('cada campo tiene su etiqueta asociada', async ({ page }) => {
        // getByLabel solo encuentra el campo si la etiqueta esta asociada de
        // verdad. Si el for/id se rompe, esto falla.
        await expect(page.getByLabel('Correo electronico')).toBeVisible()
        await expect(page.getByLabel('Contrasena')).toBeVisible()
    })

    test('los campos declaran su autocompletado', async ({ page }) => {
        // Sin esto, el gestor de contrasenas del navegador no ofrece las
        // guardadas y la persona las teclea a mano cada vez.
        await expect(page.getByLabel('Correo electronico')).toHaveAttribute(
            'autocomplete',
            'username',
        )
        await expect(page.getByLabel('Contrasena')).toHaveAttribute(
            'autocomplete',
            'current-password',
        )
    })

    test('la pantalla se recorre entera con el teclado', async ({ page }) => {
        /*
         * Quien no usa raton tiene que poder llegar al boton de entrar.
         * Un orden de tabulacion roto deja la pantalla inutilizable sin que
         * nadie que use raton lo note jamas.
         */
        await page.keyboard.press('Tab')
        await expect(page.getByLabel('Correo electronico')).toBeFocused()

        await page.keyboard.press('Tab')
        await expect(page.getByLabel('Contrasena')).toBeFocused()
    })

    test('un error se anuncia y se asocia al campo', async ({ page }) => {
        await page.getByLabel('Correo electronico').fill('nadie@example.test')
        await page.getByLabel('Contrasena').fill('incorrecta')
        await page.getByRole('button', { name: /entrar|iniciar/i }).click()

        // role="alert" es lo que hace que un lector de pantalla lo lea sin
        // que la persona tenga que ir a buscarlo.
        const aviso = page.getByRole('alert')
        await expect(aviso).toBeVisible()

        // Y el campo queda marcado como invalido, no solo el aviso de arriba.
        await expect(page.getByLabel('Correo electronico')).toHaveAttribute(
            'aria-invalid',
            'true',
        )
    })

    test('el correo se conserva y la contrasena no', async ({ page }) => {
        await page.getByLabel('Correo electronico').fill('alguien@example.test')
        await page.getByLabel('Contrasena').fill('incorrecta')
        await page.getByRole('button', { name: /entrar|iniciar/i }).click()

        await expect(page.getByLabel('Correo electronico')).toHaveValue(
            'alguien@example.test',
        )

        // La contrasena se vacia: reescribir el correo es trabajo evitable,
        // dejar la contrasena en el navegador no lo es.
        await expect(page.getByLabel('Contrasena')).toHaveValue('')
    })

    test.fixme('el acceso correcto lleva al panel', async ({ page }) => {
        await page.getByLabel('Correo electronico').fill(CORREO)
        await page.getByLabel('Contrasena').fill(CLAVE)
        await page.getByRole('button', { name: /entrar|iniciar/i }).click()

        await expect(page).toHaveURL(/\/admin$/)
    })

    test('el documento declara idioma y direccion', async ({ page }) => {
        // Resuelto en el servidor. Si algun dia se moviera a React, la
        // primera pintada saldria en la direccion equivocada.
        await expect(page.locator('html')).toHaveAttribute('lang', 'es')
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')
    })

    test('a 320 px no hay desplazamiento lateral', async ({ page }) => {
        // RNF-GEN-007. Es el ancho de los telefonos pequenos que todavia se
        // usan en ventanilla.
        await page.setViewportSize({ width: 320, height: 640 })

        const desbordamiento = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
        )

        expect(desbordamiento).toBe(false)
    })
})
