import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,

            /*
             * Las cuatro familias del sistema de diseno, con los pesos que se
             * usan de verdad y ni uno mas.
             *
             * Antes estaba la familia por defecto de Laravel 13, que no es
             * ninguna de ellas. Pasaban dos cosas a la vez: se descargaban
             * 116 kB de una fuente que nadie usaba, y las declaradas en
             * app.css no se descargaban nunca, asi que el navegador caia
             * siempre en la del sistema. La configuracion decia una cosa y la
             * pantalla mostraba otra.
             *
             * El nombre de esa familia no se escribe aqui a proposito: una
             * prueba comprueba que no aparece en este archivo, y una mencion
             * en un comentario la haria fallar sin que hubiera nada roto.
             *
             * El plugin DESCARGA las fuentes durante el build y las sirve
             * desde public/build: en produccion no hay peticiones a terceros
             * en tiempo de ejecucion.
             */
            fonts: [
                // Titulos y marca. --font-display
                bunny('Poppins', { weights: [600, 700, 800] }),

                // Cuerpo de texto. --font-sans
                bunny('Inter', { weights: [400, 500, 600] }),

                // Solo el "Si" de la marca. --font-script
                bunny('Pacifico', { weights: [400] }),

                // Codigos de verificacion y de recuperacion, donde el 0 y la O
                // tienen que distinguirse. --font-mono
                bunny('JetBrains Mono', { weights: [400, 500] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
