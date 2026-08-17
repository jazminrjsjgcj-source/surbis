/**
 * Registra el service worker del quiosco.
 *
 * Solo en /kiosk. Decision del area usuaria: cachear el panel daria una falsa
 * sensacion de que funciona sin red.
 */
export function registerKioskWorker(): void {
    if (! ('serviceWorker' in navigator)) {
        // Sin soporte, el quiosco funciona igual mientras la pestana este
        // abierta: la cola vive en IndexedDB, no en el service worker.
        return
    }

    /*
     * El scope se declara explicito.
     *
     * Sin el, un service worker servido desde la raiz controlaria TODO el
     * sitio, panel incluido. La cabecera Service-Worker-Allowed del servidor
     * lo permite; sin ella el navegador lo limitaria al directorio del
     * archivo.
     */
    void navigator.serviceWorker.register('/kiosk-sw.js', { scope: '/kiosk' })
        .catch(() => {
            // Que falle no rompe nada: se pierde el arranque sin red tras
            // reiniciar, no la captura de respuestas.
        })
}
