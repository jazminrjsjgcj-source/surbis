import { defineConfig, devices } from '@playwright/test'

/**
 * Pruebas de navegador.
 *
 * Cubren lo que una peticion HTTP no puede ver: que las etiquetas esten
 * asociadas, que los errores se anuncien, que el foco sea visible. Esa red
 * se perdio al convertir las pantallas a React (T-037), y esto es lo que la
 * recupera.
 *
 * NO cubren el panel de administracion. Eso se valida con Feature tests de
 * Laravel y pruebas de componentes: el panel lo usa gente formada que puede
 * pedir ayuda, y el quiosco lo usa quien pasa por una ventanilla y no tiene
 * a quien preguntar.
 */
export default defineConfig({
    testDir: './tests',

    // Sin reintentos en local: una prueba que pasa al segundo intento es una
    // prueba inestable, y ocultarlo es como no tenerla.
    retries: 0,

    // Un solo worker: las pruebas comparten la base encuestas_e2e y
    // paralelizarlas haria que se pisaran entre ellas.
    workers: 1,

    reporter: [['list'], ['html', { outputFolder: 'report', open: 'never' }]],

    use: {
        // Nombre del servicio en la red de Docker, no localhost: desde el
        // contenedor de Playwright, localhost es el propio contenedor.
        baseURL: process.env.E2E_BASE_URL ?? 'http://web',

        // Solo al fallar. Guardar siempre llena el disco de artefactos que
        // nadie mira.
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',

        locale: 'es-MX',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            /*
             * El quiosco corre en tabletas. Cuando lleguen sus pruebas, este
             * proyecto es el que las ejecuta con el tamano real en lugar de
             * uno de escritorio encogido.
             */
            name: 'tablet',
            use: { ...devices['iPad (gen 7)'] },
            testMatch: /kiosk|public-survey/,
        },
    ],
})
