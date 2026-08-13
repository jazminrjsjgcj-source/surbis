{{--
    Panel de organizacion.

    El panel de verdad —indicadores, que requiere atencion, que cambio— es de
    la Fase 12 y necesita respuestas que todavia no existen. Esto no lo
    sustituye: es el punto de entrada, que hasta ahora no lo tenia nadie.

    Antes usaba la vista marcador con <x-layout>, sin barra lateral. El
    administrador entraba, veia "este modulo no esta construido" y no tenia
    desde donde ir a ninguna parte: las secciones que SI existen solo se
    alcanzaban escribiendo la direccion.
--}}
<x-layouts.admin :title="__('interface.dashboard.title')"
                 :subtitle="__('interface.dashboard.subtitle')">

    <div class="card card-pad">
        <h2 class="text-lg">{{ __('interface.dashboard.available') }}</h2>
        <p class="hint mt-1 mb-4">{{ __('interface.dashboard.available_help') }}</p>

        <div class="actions">
            <a href="{{ route('admin.branches.index') }}" class="btn btn-primary">
                {{ __('interface.nav.branches') }}
            </a>

            <a href="{{ route('admin.people.index') }}" class="btn btn-ghost">
                {{ __('interface.nav.people') }}
            </a>
        </div>
    </div>

    {{--
        Se dice lo que falta y cuando llega, en lugar de dejar la pantalla
        vacia. Una pantalla vacia parece rota; una que explica su estado, no.
    --}}
    <div class="card card-pad mt-4">
        <h2 class="text-lg">{{ __('interface.dashboard.pending') }}</h2>
        <p class="hint mt-1">{{ __('interface.dashboard.pending_help') }}</p>
    </div>
</x-layouts.admin>
