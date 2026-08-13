{{--
    Vista desnuda. El sistema de diseno llega en TASK-012b: tabla, paginacion,
    filtros y estados vacios son piezas nuevas que van a reutilizar treinta
    pantallas, y construirlas bien una vez vale mas que decorar esta.
--}}
<x-layout :title="__('interface.branches.title')">
    <main>
        <h1>{{ __('interface.branches.title') }}</h1>
        <p>{{ __('interface.branches.subtitle') }}</p>

        <x-status-message />
        <x-error-summary />

        <p><a href="{{ route('admin.branches.create') }}">{{ __('interface.branches.new') }}</a></p>

        <form method="GET" action="{{ route('admin.branches.index') }}">
            <label for="q">{{ __('interface.branches.search') }}</label>
            <input id="q" name="q" type="search" value="{{ $search }}">

            <label for="status">{{ __('interface.branches.status') }}</label>
            <select id="status" name="status">
                <option value="">{{ __('interface.branches.filter_all') }}</option>
                <option value="active" @selected($status === 'active')>{{ __('interface.branches.filter_active') }}</option>
                <option value="archived" @selected($status === 'archived')>{{ __('interface.branches.filter_archived') }}</option>
            </select>

            <button type="submit">{{ __('interface.branches.search') }}</button>
        </form>

        @if ($branches->isEmpty())
            {{--
                Dos estados vacios distintos, no uno.
                "No hay sucursales" cuando de verdad no hay ninguna explica
                que son y como crear la primera. "Ninguna coincide" cuando hay
                un filtro puesto dice otra cosa y ofrece otra salida. Un solo
                mensaje para los dos casos deja al usuario creyendo que perdio
                sus datos.
            --}}
            @if ($search !== '' || $status !== '')
                <h2>{{ __('interface.branches.empty_search_title') }}</h2>
                <p>{{ __('interface.branches.empty_search_help') }}</p>
            @else
                <h2>{{ __('interface.branches.empty_title') }}</h2>
                <p>{{ __('interface.branches.empty_help') }}</p>
            @endif
        @else
            <table>
                <caption>{{ __('interface.branches.title') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('interface.branches.name') }}</th>
                        <th scope="col">{{ __('interface.branches.code') }}</th>
                        <th scope="col">{{ __('interface.branches.status') }}</th>
                        <th scope="col">{{ __('interface.branches.areas') }}</th>
                        <th scope="col">{{ __('interface.branches.people') }}</th>
                        <th scope="col">{{ __('interface.branches.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($branches as $branch)
                        <tr>
                            <td>{{ $branch->name }}</td>
                            <td>{{ $branch->code }}</td>
                            <td>
                                {{-- El estado se nombra en texto. ANEXO 1 seccion 47. --}}
                                {{ $branch->isActive()
                                    ? __('interface.branches.state_active')
                                    : __('interface.branches.state_archived') }}
                            </td>
                            <td>{{ $branch->areas_count }}</td>
                            <td>{{ $branch->memberships_count }}</td>
                            <td>
                                <a href="{{ route('admin.branches.edit', $branch) }}">
                                    {{ __('interface.branches.edit') }}
                                </a>

                                @if ($branch->isActive())
                                    <form method="POST" action="{{ route('admin.branches.archive', $branch) }}">
                                        @csrf
                                        <button type="submit">{{ __('interface.branches.archive') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.branches.activate', $branch) }}">
                                        @csrf
                                        <button type="submit">{{ __('interface.branches.activate') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $branches->links() }}
        @endif
    </main>
</x-layout>
