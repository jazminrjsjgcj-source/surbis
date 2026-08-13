{{--
    Estado vacio.

    Recibe titulo y ayuda porque NO hay un solo vacio: "todavia no hay nada"
    y "nada coincide con tu busqueda" son situaciones distintas, con causas
    distintas y salidas distintas. Un mensaje unico para las dos deja al
    usuario creyendo que perdio sus datos.
--}}
@props(['title', 'help' => null])

<div class="empty">
    <h2>{{ $title }}</h2>

    @if ($help)
        <p>{{ $help }}</p>
    @endif

    {{ $slot }}
</div>
