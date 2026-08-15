import { expect, test } from '@playwright/test'

/**
 * Constructor de preguntas. RF-AO-BLD-001 a 003 · RNF-AO-BLD-001 y 003.
 *
 * Cubren lo que una peticion HTTP no puede ver: que el autoguardado funcione,
 * que el indicador diga lo que pasa, y que reordenar sea posible SIN raton.
 *
 * Y sobre todo, lo que costo caro descubrir: que el componente CONSUMA bien
 * las props. assertInertia comprueba que los datos salen del servidor; que
 * React los lea sin reventar solo se ve aqui. Tres fallos de esta jornada
 * fueron de ese tipo, con las pruebas de servidor en verde.
 */

const CORREO = 'admin@example.test'
const CLAVE = process.env.SEED_PASSWORD ?? 'desarrollo-local'

async function entrar(page: import('@playwright/test').Page): Promise<void> {
    await page.goto('/login')
    await page.getByLabel('Correo electronico').fill(CORREO)
    await page.getByLabel('Contrasena').fill(CLAVE)
    await page.getByRole('button', { name: /entrar|iniciar/i }).click()
}

test.describe('constructor', () => {
    test.beforeEach(async ({ page }) => {
        await entrar(page)
        await page.goto('/admin/encuestas')
        await page.getByRole('link', { name: 'Satisfaccion en ventanilla' }).click()
        await page.getByRole('link', { name: 'Preguntas' }).click()
    })

    test('la pantalla carga con sus preguntas', async ({ page }) => {
        // Si el componente no consumiera bien las props, aqui saldria una
        // pantalla en blanco y ninguna prueba de servidor lo diria.
        await expect(page.getByRole('heading', { level: 1 })).toContainText('Satisfaccion')

        const lista = page.getByRole('navigation', { name: /preguntas/i })
        await expect(lista.getByRole('button')).toHaveCount(4)
    })

    test('el indicador anuncia el estado de guardado', async ({ page }) => {
        const indicador = page.getByRole('status')
        await expect(indicador).toHaveText(/guardado/i)

        await page.getByLabel('Pregunta').fill('Texto cambiado desde la prueba')

        // Primero avisa de que hay cambios, y en menos de cinco segundos
        // confirma. El debounce es de un segundo.
        await expect(indicador).toHaveText(/sin guardar|guardando/i)
        await expect(indicador).toHaveText(/guardado/i, { timeout: 5000 })
    })

    test('los cambios sobreviven a recargar', async ({ page }) => {
        await page.getByLabel('Pregunta').fill('Persistio la recarga')
        await expect(page.getByRole('status')).toHaveText(/guardado/i, { timeout: 5000 })

        await page.reload()

        await expect(page.getByLabel('Pregunta')).toHaveValue('Persistio la recarga')
    })

    test('reordenar funciona sin raton', async ({ page }) => {
        /*
         * RNF-AO-BLD-001 exige alternativa a arrastrar y soltar. Sin ella,
         * quien no usa raton no puede reordenar, y arrastrar es ademas
         * dificil con temblor o en una pantalla tactil pequena.
         */
        const lista = page.getByRole('navigation', { name: /preguntas/i })
        const primera = await lista.getByRole('button').first().textContent()

        await page.getByRole('button', { name: 'Bajar' }).first().click()

        const segunda = await lista.getByRole('button').nth(1).textContent()
        expect(segunda).toBe(primera)
    })

    test('las opciones aparecen solo en los tipos que las admiten', async ({ page }) => {
        // Que tipos las admiten lo dice el servidor. Si React lo decidiera por
        // su cuenta, ofreceria opciones que el servidor descarta al guardar.
        await expect(page.getByRole('group', { name: /opciones/i })).toBeVisible()

        await page.getByLabel('Tipo').selectOption('long_text')

        await expect(page.getByRole('group', { name: /opciones/i })).toBeHidden()
    })
})
