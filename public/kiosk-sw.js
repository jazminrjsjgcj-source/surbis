/*
 * Service worker del quiosco. Fase 10.
 *
 * SOLO cubre /kiosk. Decision del area usuaria, 18 ago 2026: cachear el
 * panel daria una falsa sensacion de que funciona sin red, cuando crear
 * encuestas, publicar y consultar respuestas exigen servidor.
 *
 * Lo que resuelve: hoy la cola vive mientras la pestana este abierta. Con
 * esto, reiniciar la tableta sin red sigue mostrando el quiosco en vez de la
 * pantalla de "sin conexion" del navegador.
 */

const VERSION = 'kiosk-v1'
const SHELL = `${VERSION}-shell`

/*
 * Lo minimo para que el quiosco arranque sin red.
 *
 * Los assets con hash —app-XXXX.js— NO se listan aqui: cambian en cada
 * compilacion y una lista fija quedaria obsoleta al dia siguiente. Se
 * guardan al vuelo cuando se piden.
 */
const ESENCIALES = ['/kiosk']

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL)
            .then((cache) => cache.addAll(ESENCIALES))
            /*
             * skipWaiting: la version nueva toma el control sin esperar a que
             * se cierren las pestanas.
             *
             * En una tableta de ventanilla encendida durante semanas, esperar
             * significaria no actualizar nunca.
             */
            .then(() => self.skipWaiting())
            .catch(() => {
                // Si falla la precarga, el service worker se instala igual:
                // sin cache es peor que con cache, pero mejor que no
                // instalarse y quedarse sin nada.
            })
    )
})

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((claves) => Promise.all(
                claves
                    .filter((clave) => !clave.startsWith(VERSION))
                    .map((clave) => caches.delete(clave))
            ))
            .then(() => self.clients.claim())
    )
})

self.addEventListener('fetch', (event) => {
    const request = event.request
    const url = new URL(request.url)

    /*
     * Solo GET del mismo origen.
     *
     * Los POST NO se tocan: el envio de respuestas ya tiene su propia cola en
     * IndexedDB, que sabe reintentar y no duplicar. Meterlos aqui crearia dos
     * mecanismos haciendo lo mismo y ninguno sabria del otro.
     */
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return
    }

    // Fuera de /kiosk el service worker no se mete.
    const esDelQuiosco = url.pathname.startsWith('/kiosk')
        || url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/storage/media/system/')

    if (!esDelQuiosco) {
        return
    }

    /*
     * Los assets con hash: primero la cache.
     *
     * Su nombre cambia cuando cambia el contenido, asi que lo guardado nunca
     * esta obsoleto —si cambio, se pide otro archivo distinto—.
     */
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/storage/')) {
        event.respondWith(
            caches.match(request).then((guardado) => guardado ?? guardarYDevolver(request))
        )

        return
    }

    /*
     * Las paginas: primero la red, con la cache de respaldo.
     *
     * Al reves que los assets, porque una pagina del quiosco cambia sin
     * cambiar de URL: servir la guardada primero mostraria la encuesta vieja
     * aunque hubiera red para traer la nueva.
     */
    event.respondWith(
        fetch(request)
            .then((respuesta) => {
                if (respuesta.ok) {
                    const copia = respuesta.clone()

                    void caches.open(SHELL).then((cache) => cache.put(request, copia))
                }

                return respuesta
            })
            .catch(() => caches.match(request).then((guardado) => guardado ?? caches.match('/kiosk')))
    )
})

function guardarYDevolver(request) {
    return fetch(request).then((respuesta) => {
        if (respuesta.ok) {
            const copia = respuesta.clone()

            void caches.open(SHELL).then((cache) => cache.put(request, copia))
        }

        return respuesta
    })
}
