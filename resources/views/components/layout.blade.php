{{--
    Estructura base de toda pantalla del sistema.

    lang y dir salen del idioma activo, no escritos a mano. Es lo que hace que
    anadir arabe no obligue a tocar cada plantilla. ANEXO 1 secciones 49 y 50.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ \App\Domain\Shared\Localization\TextDirection::current() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

    {{--
        Vite::fonts() emite los @font-face de las familias declaradas en
        vite.config.js. Devuelve un bloque <style> embebido, no un <link>, asi
        que el navegador puede empezar a pedir las fuentes sin esperar a
        descargar otra hoja.

        Va ANTES de @vite: las declaraciones tienen que existir cuando llega la
        hoja que las usa.

        Sin esta linea, las fuentes se compilaban y quedaban en public/build
        sin que ninguna pagina las pidiera. No daba error: la pagina cargaba,
        se veia bien, y el navegador caia en la tipografia del sistema. Se
        detecto mirando el listado de archivos generados, no el codigo.
    --}}
    {!! Vite::fonts() !!}

    {{--
        Se pide app.tsx y no app.css.

        Al pasar a Inertia, el unico punto de entrada de Vite es app.tsx, que
        importa app.css. `resources/css/app.css` dejo de existir en el
        manifiesto, y estas plantillas seguian pidiendolo: error 500 en cada
        pantalla que todavia usa Blade.

        Esto carga React en pantallas que no lo necesitan —208 kB de mas—
        mientras dure la transicion. Es temporal a proposito: cada pantalla
        convertida deja de usar este layout, y cuando no quede ninguna, el
        archivo se borra. Que la convivencia moleste un poco es lo que empuja
        a terminarla.
    --}}
    @vite('resources/js/app.tsx')
</head>
<body class="min-h-screen">
    {{ $slot }}
</body>
</html>
