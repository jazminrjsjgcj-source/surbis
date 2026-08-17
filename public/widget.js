/*
 * El widget. RF-AO-DEP-002 · decision del area usuaria, 19 ago 2026.
 *
 * Inserta un IFRAME con la URL publica del deployment. No inyecta HTML en la
 * pagina anfitriona a proposito: dentro del iframe, ni el CSS ni el
 * JavaScript del sitio pueden romper la encuesta, y la encuesta tampoco puede
 * tocar el sitio.
 *
 * Se usa asi:
 *
 *   <div data-encuesta="TOKEN"></div>
 *   <script src="https://…/widget.js" async></script>
 */
(function () {
    'use strict'

    /*
     * El origen sale del SRC de este script, no de una constante.
     *
     * Asi el mismo archivo sirve en desarrollo y en produccion sin tocarlo, y
     * —lo que importa— el origen que se valida despues es exactamente de
     * donde vino el codigo.
     */
    var script = document.currentScript

    if (!script) {
        return
    }

    var origen = new URL(script.src).origin

    function montar(contenedor) {
        var token = contenedor.getAttribute('data-encuesta')

        if (!token || contenedor.getAttribute('data-montado')) {
            return
        }

        contenedor.setAttribute('data-montado', '1')

        var iframe = document.createElement('iframe')

        iframe.src = origen + '/e/' + encodeURIComponent(token) + '?widget=1'
        iframe.style.width = '100%'
        iframe.style.border = '0'

        /*
         * Altura inicial razonable mientras carga.
         *
         * Sin ella el iframe mide 150px por defecto y la encuesta aparece
         * cortada durante el primer segundo.
         */
        iframe.style.height = '520px'
        iframe.setAttribute('title', 'Encuesta')
        iframe.setAttribute('loading', 'lazy')

        /*
         * sandbox con lo MINIMO que la encuesta necesita.
         *
         * allow-forms y allow-scripts para que funcione; allow-same-origin
         * para que pueda usar su cookie y su IndexedDB. NO se dan
         * allow-top-navigation ni allow-popups: una encuesta no tiene por que
         * poder sacar a nadie de la pagina donde esta.
         */
        iframe.setAttribute(
            'sandbox',
            'allow-forms allow-scripts allow-same-origin'
        )

        contenedor.appendChild(iframe)

        return iframe
    }

    var iframes = []
    var contenedores = document.querySelectorAll('[data-encuesta]')

    for (var i = 0; i < contenedores.length; i++) {
        var creado = montar(contenedores[i])

        if (creado) {
            iframes.push(creado)
        }
    }

    if (iframes.length === 0) {
        return
    }

    /*
     * La altura la pide la encuesta con postMessage.
     *
     * VALIDANDO EL ORIGEN, que es lo unico que impide que cualquier otra
     * pagina o iframe de la web anfitriona mande mensajes haciendose pasar
     * por la encuesta. Sin esta comprobacion, un anuncio de terceros en la
     * misma pagina podria redimensionar el widget a cero y hacerlo
     * desaparecer.
     */
    window.addEventListener('message', function (evento) {
        if (evento.origin !== origen) {
            return
        }

        var datos = evento.data

        if (!datos || datos.tipo !== 'encuesta:altura') {
            return
        }

        var altura = parseInt(datos.altura, 10)

        // Un limite por arriba y otro por abajo: un mensaje con altura 0
        // esconderia la encuesta, y uno con 99999 rompería la pagina.
        if (isNaN(altura) || altura < 200 || altura > 5000) {
            return
        }

        for (var j = 0; j < iframes.length; j++) {
            if (iframes[j].contentWindow === evento.source) {
                iframes[j].style.height = altura + 'px'
            }
        }
    })
})()
