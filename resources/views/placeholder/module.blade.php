{{--
    Marcador. Existe para que la redireccion por rol de RF-AUT-003 tenga un
    destino real que probar. Cada uno se sustituye por su modulo en su fase.
--}}
<x-layout :title="$module">
    <h1>{{ $module }}</h1>
    <p>{{ __('interface.placeholder.not_built') }}</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">{{ __('interface.session.logout') }}</button>
    </form>
</x-layout>
