import { expect, test } from '@playwright/test'

import { entrar } from './helpers'

/**
 * El marco de administracion y la tabla. RNF-GEN-006 · ANEXO 1 seccion 47.
 *
 * Recupera lo que AdminShellTest dejo de poder comprobar al pasar a React:
 * caption, scope="col", aria-current. Ese marcado lo genera el navegador, y
 * una peticion HTTP no puede verlo.
 */
test.describe('marco de administracion', () => {
    test.beforeEach(async ({ page }) => {
        await entrar(page)
        await page.goto('/admin/sucursales')
    })

    test('la seccion activa se anuncia, no solo se colorea', async ({ page }) => {
        /*
         * aria-current y no solo un color de fondo. Quien no ve la pantalla
         * tambien tiene que saber donde esta.
         */
        const nav = page.getByRole('navigation', { name: /secciones/i })

        await expect(nav.getByRole('link', { name: 'Sucursales' })).toHaveAttribute(
            'aria-current',
            'page',
        )
    })

    test('la tabla tiene titulo y encabezados de columna', async ({ page }) => {
        /*
         * Sin caption, un lector de pantalla anuncia "tabla de 6 columnas"
         * sin decir de que. Y sin scope="col" cada celda se lee suelta, sin
         * relacionarla con su cabecera.
         */
        const tabla = page.getByRole('table')

        await expect(tabla.locator('caption')).not.toBeEmpty()

        const cabeceras = tabla.locator('thead th')
        await expect(cabeceras).toHaveCount(6)

        for (const th of await cabeceras.all()) {
            await expect(th).toHaveAttribute('scope', 'col')
        }
    })

    test('el estado se dice en palabras, no solo en color', async ({ page }) => {
        // ANEXO 1 seccion 47: el color no puede ser el unico portador.
        await expect(page.getByText('Activa').first()).toBeVisible()
        await expect(page.getByText('Archivada').first()).toBeVisible()
    })

    test('los filtros sobreviven al aplicarse', async ({ page }) => {
        await page.getByLabel(/buscar/i).fill('CENTRO')
        await page.getByRole('button', { name: 'Aplicar' }).click()

        await expect(page).toHaveURL(/q=CENTRO/)
        await expect(page.getByLabel(/buscar/i)).toHaveValue('CENTRO')
    })

    test('un filtro sin resultados dice algo distinto de no haber nada', async ({ page }) => {
        /*
         * Son dos vacios distintos. Un mensaje unico para los dos deja al
         * usuario creyendo que perdio sus datos.
         */
        await page.getByLabel(/buscar/i).fill('inexistente-zzz')
        await page.getByRole('button', { name: 'Aplicar' }).click()

        await expect(page.getByText(/ninguna sucursal coincide/i)).toBeVisible()
        await expect(page.getByRole('link', { name: /quitar filtros/i })).toBeVisible()
    })
})
