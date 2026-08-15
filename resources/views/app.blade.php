{{--
    Unica plantilla Blade de la aplicacion.

    lang y dir se resuelven en el servidor: el navegador recibe el documento ya
    con la direccion correcta. Si se decidiera en React, la primera pintada
    saldria en la direccion equivocada y el arabe daria un salto visible al
    cargar. ANEXO 1 secciones 49 y 50.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ \App\Domain\Shared\Localization\TextDirection::current() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{--
        @inertia CON parentesis, aunque no lleve argumentos.

        Sin ellos, Blade le pasa el nombre de la vista raiz —"app"— como
        argumento, y ese texto acaba escrito literalmente en data-page. El
        cliente de Inertia lee "app" donde espera un JSON, no encuentra la
        clave component y revienta con "Cannot read properties of null".

        El detalle: las 227 pruebas de PHPUnit pasaban igual, porque
        assertInertia lee la RESPUESTA del servidor y no el HTML renderizado.
        El servidor producia los datos bien; lo que fallaba era como la
        plantilla los escribia. Ninguna prueba de servidor puede ver eso.
    --}}
    <title inertia>{{ config('app.name') }}</title>

    {{-- Los @font-face antes de la hoja que los usa. Ver T-030. --}}
    {!! Vite::fonts() !!}

    @vite('resources/js/app.tsx')
    @inertiaHead()
</head>
<body class="min-h-screen">
    @inertia()
</body>
</html>
