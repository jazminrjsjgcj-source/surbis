<x-layouts.admin :title="__('interface.branches.title')"
                 :subtitle="__('interface.branches.subtitle')">

    <div class="toolbar">
        <form method="GET" action="{{ route('admin.branches.index') }}" class="toolbar contents">
            <div class="field toolbar-grow">
                <label for="q">{{ __('interface.branches.search') }}</label>
                <input id="q" name="q" type="search" class="input" value="{{ $search }}">
            </div>

            <div class="field">
                <label for="status">{{ __('interface.branches.status') }}</label>
                <select id="status" name="status" class="input">
                    <option value="">{{ __('interface.branches.filter_all') }}</option>
                    <option value="active" @selected($status === 'active')>
                        {{ __('interface.branches.filter_active') }}
                    </option>
                    <option value="archived" @selected($status === 'archived')>
                        {{ __('interface.branches.filter_archived') }}
                    </option>
                </select>
            </div>

            <button type="submit" class="btn btn-ghost">{{ __('interface.branches.apply') }}</button>
        </form>

        <a href="{{ route('admin.branches.create') }}" class="btn btn-primary ms-auto">
            {{ __('interface.branches.new') }}
        </a>
    </div>

    @if ($branches->isEmpty())
        <div class="card card-pad">
            @if ($search !== '' || $status !== '')
                <x-empty-state :title="__('interface.branches.empty_search_title')"
                               :help="__('interface.branches.empty_search_help')">
                    <a href="{{ route('admin.branches.index') }}" class="btn btn-ghost">
                        {{ __('interface.branches.clear_filters') }}
                    </a>
                </x-empty-state>
            @else
                <x-empty-state :title="__('interface.branches.empty_title')"
                               :help="__('interface.branches.empty_help')">
                    <a href="{{ route('admin.branches.create') }}" class="btn btn-primary">
                        {{ __('interface.branches.new') }}
                    </a>
                </x-empty-state>
            @endif
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <caption class="ps-3.5 pt-3">{{ __('interface.branches.caption') }}</caption>

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
                                <td class="table-numeric">{{ $branch->code }}</td>

                                <td>
                                    {{-- El estado se nombra en texto dentro de
                                         la etiqueta: el color acompana, no
                                         informa. ANEXO 1 seccion 47. --}}
                                    @if ($branch->isActive())
                                        <span class="badge badge-active">
                                            {{ __('interface.branches.state_active') }}
                                        </span>
                                    @else
                                        <span class="badge badge-archived">
                                            {{ __('interface.branches.state_archived') }}
                                        </span>
                                    @endif
                                </td>

                                <td class="table-numeric">
                                    {{-- El conteo enlaza en lugar de quedarse
                                         en un numero que no lleva a ningun
                                         sitio. --}}
                                    <a href="{{ route('admin.areas.index', $branch) }}"
                                       class="text-primary">{{ $branch->areas_count }}</a>
                                </td>
                                <td class="table-numeric">{{ $branch->memberships_count }}</td>

                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('admin.branches.edit', $branch) }}"
                                           class="text-primary text-sm">
                                            {{ __('interface.branches.edit') }}
                                        </a>

                                        @if ($branch->isActive())
                                            <form method="POST"
                                                  action="{{ route('admin.branches.archive', $branch) }}">
                                                @csrf
                                                <button type="submit" class="text-ink-muted text-sm">
                                                    {{ __('interface.branches.archive') }}
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST"
                                                  action="{{ route('admin.branches.activate', $branch) }}">
                                                @csrf
                                                <button type="submit" class="text-primary text-sm">
                                                    {{ __('interface.branches.activate') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{ $branches->links() }}
    @endif
</x-layouts.admin>
