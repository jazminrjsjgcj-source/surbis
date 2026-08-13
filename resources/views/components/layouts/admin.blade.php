{{--
    Marco de las pantallas de administracion.

    La navegacion lista SOLO lo que existe. Es tentador poner las ocho
    secciones del prototipo con las que faltan en gris, pero un menu que
    promete pantallas que no estan es un mecanismo que no hace nada: el
    usuario hace clic y no pasa nada, o llega a un error. Cada fase anade su
    entrada cuando tiene algo detras.
--}}
@props(['title', 'subtitle' => null])

<x-layout :title="$title">
    <div class="shell">
        <nav class="shell-nav" aria-label="{{ __('interface.nav.label') }}">
            <p class="brand">
                <span class="brand-mark" aria-hidden="true">P</span>
                <span class="font-display font-bold">Pulso</span>
            </p>

            <div class="nav-section">
                <p class="nav-label">{{ __('interface.nav.organization') }}</p>

                <a href="{{ route('admin.dashboard') }}"
                   class="nav-link"
                   @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                    {{ __('interface.nav.dashboard') }}
                </a>

                <a href="{{ route('admin.branches.index') }}"
                   class="nav-link"
                   @if (request()->routeIs('admin.branches.*') || request()->routeIs('admin.areas.*')) aria-current="page" @endif>
                    {{ __('interface.nav.branches') }}
                </a>

                <a href="{{ route('admin.people.index') }}"
                   class="nav-link"
                   @if (request()->routeIs('admin.people.*')) aria-current="page" @endif>
                    {{ __('interface.nav.people') }}
                </a>
            </div>
        </nav>

        <div class="shell-main">
            <header class="shell-topbar">
                <a href="{{ route('account.security') }}" class="text-sm text-primary">
                    {{ __('interface.nav.security') }}
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">
                        {{ __('interface.session.logout') }}
                    </button>
                </form>
            </header>

            <main class="shell-content">
                <div class="page-header">
                    <h1>{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="hint mt-1">{{ $subtitle }}</p>
                    @endif
                </div>

                <x-status-message />
                <x-error-summary />

                {{ $slot }}
            </main>
        </div>
    </div>
</x-layout>
