import { expect, test } from '@playwright/test'

import { entrar } from './helpers'

/**
 * Personas. D-018 · RNF-GEN-006.
 *
 * PersonTest dejo anotado que "quien no tiene cuenta lo dice en lugar de
 * dejarlo vacio" solo lo puede ver una prueba de navegador: el servidor manda
 * email null y has_account false, y que eso se convierta en las palabras
 * correctas lo decide el componente.
 */
test.describe('personas', () => {
    test.beforeEach(async ({ page }) => {
        await entrar(page)
        await page.goto('/admin/personas')
    })

    test('quien no tiene cuenta lo dice con palabras', async ({ page }) => {
        // Un guion no distingue "no tiene" de "no se sabe" de "no aplica".
        await expect(page.getByText('No inicia sesion').first()).toBeVisible()
        await expect(page.getByText('Solo se evalua').first()).toBeVisible()
    })

    test('la tabla distingue los tres tipos de persona', async ({ page }) => {
        // D-018: cuenta, cuenta que ademas se evalua, y solo evaluada.
        const tabla = page.getByRole('table')

        await expect(tabla).toContainText('Cuenta')
        await expect(tabla).toContainText('Solo se evalua')
    })

    test('asignar sucursal encadena las areas', async ({ page }) => {
        /*
         * Las areas dependen de la sucursal. Al cambiar de sede el area se
         * limpia: una de otra sucursal no tiene sentido y el servidor la
         * rechazaria, produciendo un error que nadie relacionaria con haber
         * cambiado el desplegable de arriba.
         */
        await page.getByRole('button', { name: 'Asignar' }).first().click()

        const areas = page.getByLabel('Area')
        await expect(areas).toBeDisabled()

        await page.getByLabel('Sucursal').selectOption({ index: 1 })
        await expect(areas).toBeEnabled()
    })
})
