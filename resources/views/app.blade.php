{{--
    Unica plantilla Blade que queda en el sistema.

    lang y dir siguen resolviendose en el servidor: el navegador recibe el
    documento ya con la direccion correcta. Si se decidiera en React, la
    primera pintada saldria en la direccion equivocada y el arabe daria un
    salto visible al cargar. ANEXO 1 secciones 49 y 50.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ \App\Domain\Shared\Localization\TextDirection::current() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name') }}</title>

    {{-- Los @font-face antes de la hoja que los usa. Ver T-030. --}}
    {!! Vite::fonts() !!}

    @vite('resources/js/app.tsx')
    @inertiaHead
</head>
<body class="min-h-screen">
    @inertia
</body>
</html>
