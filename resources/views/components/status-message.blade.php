{{--
    Mensaje de exito de una accion anterior.

    role="status" y no "alert": se anuncia sin interrumpir lo que el lector de
    pantalla este leyendo, porque no es un error. Usar "alert" para todo
    convierte cada confirmacion en una interrupcion.
--}}
@if (session('status'))
    <div class="alert alert-ok mb-4" role="status">
        {{ session('status') }}
    </div>
@endif
