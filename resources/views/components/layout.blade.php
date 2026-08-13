{{--
    Estructura minima y deliberadamente sin estilos. El sistema de diseno
    llega en el segundo paquete de TASK-009; esto es lo que sostiene las
    pruebas de comportamiento mientras tanto.

    lang y dir salen del idioma activo, no escritos a mano: es lo que hace que
    anadir arabe no obligue a tocar cada plantilla. ANEXO 1 seccion 50.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ \App\Domain\Shared\Localization\TextDirection::current() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body>
    <main>
        {{ $slot }}
    </main>
</body>
</html>
