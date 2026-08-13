<x-layouts.admin :title="__('interface.people.title')"
                 :subtitle="__('interface.people.subtitle')">

    <div class="toolbar">
        <form method="GET" action="{{ route('admin.people.index') }}" class="toolbar contents">
            <div class="field toolbar-grow">
                <label for="q">{{ __('interface.people.search') }}</label>
                <input id="q" name="q" type="search" class="input" value="{{ $search }}">
            </div>

            <div class="field">
                <label for="type">{{ __('interface.people.kind') }}</label>
                <select id="type" name="type" class="input">
                    <option value="">{{ __('interface.people.filter_all') }}</option>
                    <option value="accounts" @selected($filter === 'accounts')>
                        {{ __('interface.people.filter_accounts') }}
                    </option>
                    <option value="evaluated" @selected($filter === 'evaluated')>
                        {{ __('interface.people.filter_evaluated') }}
                    </option>
                </select>
            </div>

            <button type="submit" class="btn btn-ghost">{{ __('interface.people.apply') }}</button>
        </form>

        <a href="{{ route('admin.people.create') }}" class="btn btn-primary ms-auto">
            {{ __('interface.people.invite') }}
        </a>
    </div>

    @if ($rows->isEmpty())
        <div class="card card-pad">
            @if ($search !== '' || $filter !== '')
                <x-empty-state :title="__('interface.people.empty_search_title')"
                               :help="__('interface.people.empty_search_help')">
                    <a href="{{ route('admin.people.index') }}" class="btn btn-ghost">
                        {{ __('interface.people.clear_filters') }}
                    </a>
                </x-empty-state>
            @else
                <x-empty-state :title="__('interface.people.empty_title')"
                               :help="__('interface.people.empty_help')">
                    <a href="{{ route('admin.people.create') }}" class="btn btn-primary">
                        {{ __('interface.people.invite') }}
                    </a>
                </x-empty-state>
            @endif
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <caption class="ps-3.5 pt-3">{{ __('interface.people.caption') }}</caption>

                    <thead>
                        <tr>
                            <th scope="col">{{ __('interface.people.name') }}</th>
                            <th scope="col">{{ __('interface.people.kind') }}</th>
                            <th scope="col">{{ __('interface.people.role') }}</th>
                            <th scope="col">{{ __('interface.people.branch') }}</th>
                            <th scope="col">{{ __('interface.people.status') }}</th>
                            <th scope="col">{{ __('interface.people.actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    {{ $row->name }}

                                    {{-- El correo solo si lo hay. Quien no
                                         tiene cuenta no tiene correo de
                                         acceso, y una celda vacia no lo
                                         explica. --}}
                                    @if ($row->email)
                                        <span class="hint block">{{ $row->email }}</span>
                                    @else
                                        <span class="hint block">{{ __('interface.people.no_login') }}</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($row->hasAccount() && $row->isEvaluated())
                                        {{ __('interface.people.kind_account_evaluated') }}
                                    @elseif ($row->hasAccount())
                                        {{ __('interface.people.kind_account') }}
                                    @else
                                        {{ __('interface.people.kind_evaluated') }}
                                    @endif
                                </td>

                                <td>
                                    @if ($row->membership)
                                        {{ $row->membership->isAdmin()
                                            ? __('interface.people.role_admin')
                                            : __('interface.people.role_collaborator') }}
                                    @else
                                        <span class="hint">{{ __('interface.people.no_login') }}</span>
                                    @endif
                                </td>

                                <td>
                                    {{ $row->branchName ?? __('interface.people.no_branch') }}
                                    @if ($row->areaName)
                                        <span class="hint block">{{ $row->areaName }}</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($row->membership === null)
                                        <span class="hint">&nbsp;</span>
                                    @elseif ($row->membership->isActive())
                                        <span class="badge badge-active">
                                            {{ __('interface.people.state_active') }}
                                        </span>
                                    @elseif ($row->membership->joined_at === null)
                                        {{-- Suspendida y nunca usada: es una
                                             invitacion pendiente, no un
                                             castigo. Decir "suspendida"
                                             aqui confundiria. --}}
                                        <span class="badge badge-archived">
                                            {{ __('interface.people.state_invited') }}
                                        </span>
                                    @else
                                        <span class="badge badge-archived">
                                            {{ __('interface.people.state_suspended') }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    @if ($row->membership)
                                        <div class="flex flex-wrap items-center gap-2">
                                            @can('suspend', $row->membership)
                                                @if ($row->membership->isActive())
                                                    <form method="POST"
                                                          action="{{ route('admin.people.suspend', $row->membership) }}">
                                                        @csrf
                                                        <button type="submit" class="text-ink-muted text-sm">
                                                            {{ __('interface.people.suspend') }}
                                                        </button>
                                                    </form>
                                                @else
                                                    <form method="POST"
                                                          action="{{ route('admin.people.activate', $row->membership) }}">
                                                        @csrf
                                                        <button type="submit" class="text-primary text-sm">
                                                            {{ __('interface.people.activate') }}
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                <span class="hint">{{ __('interface.people.self') }}</span>
                                            @endcan
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.admin>
