import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            /*
             * Un solo punto de entrada: app.tsx importa app.css.
             *
             * Antes eran dos, uno de ellos un app.js vacio que Laravel genera
             * y que nadie llenaba nunca.
             */
            input: ['resources/js/app.tsx'],
            refresh: true,

            /*
             * Las cuatro familias del sistema de diseno, con los pesos que se
             * usan de verdad y ni uno mas.
             *
             * El plugin DESCARGA las fuentes durante el build y las sirve
             * desde public/build: en produccion no hay peticiones a terceros
             * en tiempo de ejecucion.
             */
            fonts: [
                bunny('Poppins', { weights: [600, 700, 800] }),
                bunny('Inter', { weights: [400, 500, 600] }),
                bunny('Pacifico', { weights: [400] }),
                bunny('JetBrains Mono', { weights: [400, 500] }),
            ],
        }),
        react(),
        tailwindcss(),
    ],

    resolve: {
        alias: {
            // El mismo alias que declara tsconfig.json. Si solo estuviera en
            // uno de los dos, TypeScript compilaria y Vite no encontraria
            // nada, o al reves.
            '@': path.resolve(import.meta.dirname, 'resources/js'),
        },
    },

    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
