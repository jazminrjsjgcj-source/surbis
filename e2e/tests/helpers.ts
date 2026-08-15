import type { Page } from '@playwright/test'

const CORREO = 'admin@example.test'
const CLAVE = process.env.SEED_PASSWORD ?? 'desarrollo-local'

/**
 * Entrar como administrador de organizacion.
 *
 * Vive aqui y no repetido en cada archivo: si el flujo de acceso cambia
 * —otro campo, otro paso— hay UN sitio que tocar. Repetirlo en cinco
 * archivos garantiza que alguno se quede atras y falle por un motivo que no
 * tiene que ver con lo que prueba.
 */
export async function entrar(page: Page, correo: string = CORREO): Promise<void> {
    await page.goto('/login')
    await page.getByLabel('Correo electronico').fill(correo)
    await page.getByLabel('Contrasena').fill(CLAVE)
    await page.getByRole('button', { name: /entrar|iniciar/i }).click()

    /*
     * ESPERAR a que la navegacion termine.
     *
     * click() vuelve cuando el evento se despacha, no cuando el servidor ha
     * respondido y el navegador ha guardado la cookie de sesion. Un goto()
     * inmediato despues aborta esa peticion en vuelo, y la pantalla siguiente
     * llega sin sesion: sale el acceso otra vez.
     *
     * El sintoma engana porque no menciona sesiones: dieciocho pruebas
     * esperando treinta segundos un boton que existe, en una pantalla que no
     * es la que creen estar mirando.
     */
    await page.waitForURL(/\/admin|\/verificacion|\/organizaciones/)
}
