import { expect, test } from '@playwright/test'

/**
 * Recuperar contrasena. RF-AUT-008 a 013 · RNF-AUT-007.
 *
 * PasswordResetTest dejo anotado que el enlace desde el acceso solo lo ve una
 * prueba de navegador: la URL viaja como prop y que el componente la pinte
 * como enlace no lo comprueba nadie desde el servidor.
 */
test.describe('recuperar contrasena', () => {
    test('el acceso ofrece recuperar la contrasena', async ({ page }) => {
        // La cadena 'interface.login.forgot' llevo dos entregas escrita sin
        // que ninguna pantalla la mostrara. Por eso existe esta prueba.
        await page.goto('/login')

        const enlace = page.getByRole('link', { name: /olvide mi contrasena/i })
        await expect(enlace).toBeVisible()

        await enlace.click()
        await expect(page).toHaveURL(/recuperar-contrasena/)
    })

    test('el campo de correo tiene etiqueta y autocompletado', async ({ page }) => {
        await page.goto('/recuperar-contrasena')

        const correo = page.getByLabel('Correo electronico')
        await expect(correo).toBeVisible()
        await expect(correo).toHaveAttribute('autocomplete', 'username')
    })

    test('la respuesta es la misma exista o no la cuenta', async ({ page }) => {
        /*
         * RNF-AUT-007. Si aqui se distinguiera entre "enviado" y "ese correo
         * no existe", la pantalla se convertiria en un comprobador de que
         * direcciones estan registradas.
         */
        await page.goto('/recuperar-contrasena')
        await page.getByLabel('Correo electronico').fill('nadie-existe-zzz@example.test')
        await page.getByRole('button').filter({ hasText: /enviar/i }).click()

        const aviso = page.getByRole('status')
        await expect(aviso).toBeVisible()

        const textoInexistente = await aviso.textContent()

        await page.goto('/recuperar-contrasena')
        await page.getByLabel('Correo electronico').fill('admin@example.test')
        await page.getByRole('button').filter({ hasText: /enviar/i }).click()

        await expect(page.getByRole('status')).toHaveText(textoInexistente ?? '')
    })
})
