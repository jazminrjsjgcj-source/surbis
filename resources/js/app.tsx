import '../css/app.css'

import { registerKioskWorker } from '@/lib/kioskWorker'
import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'

createInertiaApp({
    title: (title) => (title ? `${title} · Encuestas` : 'Encuestas'),

    /*
     * ./pages en minuscula, que es donde las busca Inertia por defecto
     * (config/inertia.php, pages.paths => resource_path('js/pages')).
     *
     * Estuvieron en ./Pages un rato. En Linux eso es otro directorio, asi que
     * la comprobacion de assertInertia no encontraba el componente aunque el
     * archivo existiera. En Windows habria funcionado, que es exactamente lo
     * que el ANEXO 1 seccion 25 avisa.
     *
     * Se sigue la convencion del paquete en lugar de declarar la nuestra: de
     * lo contrario, cada ejemplo de la documentacion de Inertia habria que
     * traducirlo antes de usarlo.
     */
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.tsx', { eager: true })
        const page = pages[`./pages/${name}.tsx`]

        if (!page) {
            // Un fallo silencioso aqui deja la pantalla en blanco sin decir
            // por que. Mejor que reviente nombrando la pagina que falta.
            throw new Error(`No existe la pagina ./pages/${name}.tsx`)
        }

        return page
    },

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />)
    },
})

/*
 * El quiosco necesita arrancar sin red tras un reinicio. El resto del
 * sistema no: sin conexion no hay nada que administrar, y cachearlo daria
 * una falsa sensacion de que funciona.
 */
if (window.location.pathname.startsWith('/kiosk')) {
    registerKioskWorker()
}
