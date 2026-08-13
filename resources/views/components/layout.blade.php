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
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen">
    {{ $slot }}
</body>
</html>
