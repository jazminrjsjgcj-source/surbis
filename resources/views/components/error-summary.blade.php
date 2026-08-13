{{--
    Resumen de errores.

    Va ANTES del formulario y con role="alert" para que un lector de pantalla
    lo anuncie al recargar la pagina. Sin el, quien no ve la pantalla no se
    entera de que el envio fallo. RNF-AUT-004 y RNF-GEN-006.

    El titulo nombra el estado en texto: el color no es el unico portador.
--}}
@if ($errors->any())
    <div class="alert alert-error mb-4" role="alert">
        <p class="alert-title">{{ __('interface.errors.summary') }}</p>

        <ul class="ps-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
