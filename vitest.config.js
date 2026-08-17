import { defineConfig, mergeConfig } from 'vitest/config'
import path from 'node:path'

import viteConfig from './vite.config.js'

/*
 * Vitest reutiliza la configuracion de Vite.
 *
 * mergeConfig y no una configuracion aparte: el alias '@' ya esta declarado
 * en vite.config.js y en tsconfig.json. Escribirlo por tercera vez seria una
 * tercera verdad sobre lo mismo, y el dia que cambie una de las tres las
 * pruebas dejarian de encontrar los archivos sin decir por que.
 */
export default mergeConfig(
    viteConfig,
    defineConfig({
        /*
         * Los plugins de Vite NO valen aqui.
         *
         * laravel-vite-plugin descarga fuentes y busca el manifiesto de
         * Laravel; tailwindcss compila CSS. En una prueba de logica los dos
         * sobran, y el de Laravel falla al no encontrar un servidor.
         *
         * Se conserva solo react(), que es lo que transforma el JSX.
         */
        plugins: viteConfig.plugins.filter(
            (plugin) => plugin && plugin.name === 'vite:react-babel',
        ),

        test: {
            /*
             * jsdom: un DOM simulado.
             *
             * Ve la estructura y responde a los clics, pero NO aplica CSS.
             * Los 44 px del quiosco, el contraste y si algo tapa a otra cosa
             * se le escapan: eso solo lo ve un navegador de verdad.
             */
            environment: 'jsdom',

            globals: true,
            setupFiles: ['./resources/js/tests/setup.ts'],

            include: ['resources/js/**/*.test.{ts,tsx}'],

            // Los assets de Vite no existen en pruebas.
            css: false,
        },

        resolve: {
            alias: {
                '@': path.resolve(import.meta.dirname, 'resources/js'),
            },
        },
    }),
)
