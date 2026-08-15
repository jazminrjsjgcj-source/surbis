import { expect, test } from '@playwright/test'

/**
 * Constructor de preguntas. RF-AO-BLD-001 a 003 y RNF-AO-BLD-001 y 003.
 *
 * Estas pruebas cubren lo que una peticion HTTP no puede ver: que el
 * autoguardado funcione, que el indicador diga lo que pasa, y que reordenar
 * sea posible SIN raton.
 *
 * El endpoint ya esta cubierto por BuilderEndpointTest en PHPUnit. Aqui se
 * mira el navegador.
 */

const CORREO = 'admin@example.test'
const CLAVE = process.env.SEED_PASSWORD ?? 'desarrollo-local'

test.describe('constructor', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login')
        await page.getByLabel('Correo electronico').fill(CORREO)
        await page.getByLabel('Contrasena').fill(CLAVE)
        await page.getByRole('button', { name: /entrar|iniciar/i }).click()
    })

    test.fixme('el indicador anuncia el estado de guardado', async ({ page }) => {
        /*
         * PENDIENTE hasta que exista una encuesta sembrada.
         *
         * DevelopmentSeeder no crea ninguna: se escribio antes de la Fase 3.
         * Escribir la prueba contra una encuesta que hay que crear a mano la
         * haria depender del estado de la base, y eso es justo lo que hace
         * que una suite E2E se vuelva inestable y acabe desactivada.
         *
         * Lo que falta: anadir una encuesta con preguntas al seeder. Es una
         * tarea pequena y va antes que estas pruebas.
         */
        await expect(page.getByRole('status')).toHaveText(/guardado/i)
    })

    test.fixme('reordenar funciona sin raton', async ({ page }) => {
        // RNF-AO-BLD-001. Misma dependencia del seeder.
        await expect(page.getByRole('button', { name: /subir/i })).toBeVisible()
    })
})
