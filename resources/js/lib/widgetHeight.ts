/**
 * Le dice al anfitrion cuanto mide la encuesta.
 *
 * Solo cuando esta dentro de un iframe con ?widget=1: fuera de ahi no hay
 * nadie escuchando, y mandar mensajes a la nada en cada cambio de pantalla
 * seria trabajo por nada.
 */
export function reportWidgetHeight(): () => void {
    const dentroDeWidget = window.self !== window.top
        && new URLSearchParams(window.location.search).has('widget')

    if (! dentroDeWidget) {
        return () => {}
    }

    function informar(): void {
        /*
         * scrollHeight del documento, no innerHeight.
         *
         * innerHeight es lo que MIDE el iframe ahora, asi que usarlo lo
         * dejaria clavado en su tamano inicial: nunca creceria.
         */
        const altura = Math.ceil(document.documentElement.scrollHeight)

        /*
         * targetOrigin '*' porque el widget puede estar en CUALQUIER sitio:
         * es su razon de ser.
         *
         * Lo que se manda es una altura, un numero sin ningun valor para
         * quien lo intercepte. Quien SI valida el origen es el anfitrion, que
         * es donde importa: sin esa comprobacion, otro iframe podria mandar
         * alturas falsas.
         */
        window.parent.postMessage({ tipo: 'encuesta:altura', altura }, '*')
    }

    informar()

    /*
     * ResizeObserver y no un temporizador: la encuesta cambia de alto al
     * avanzar de pregunta, y un reloj cada segundo daria saltos visibles.
     */
    const observador = new ResizeObserver(informar)

    observador.observe(document.documentElement)

    return () => observador.disconnect()
}
