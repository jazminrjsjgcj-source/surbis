{{--
    Marcador. Existe para que la redireccion por rol de RF-AUT-003 tenga un
    destino real que probar. Cada uno se sustituye por su modulo en su fase.
--}}
<x-layout :title="$module">
    <div class="grid min-h-screen place-items-center p-6">
        <div class="card card-pad w-full max-w-100 text-center">
            <h1 class="text-xl">{{ $module }}</h1>
            <p class="hint mt-2 mb-4">{{ __('interface.placeholder.not_built') }}</p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-ghost">
                    {{ __('interface.session.logout') }}
                </button>
            </form>
        </div>
    </div>
</x-layout>
