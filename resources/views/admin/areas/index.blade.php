<x-layouts.admin :title="__('interface.areas.title', ['branch' => $branch->name])"
                 :subtitle="__('interface.areas.subtitle')">

    <p class="mb-4">
        <a href="{{ route('admin.branches.index') }}" class="text-primary text-sm">
            {{ __('interface.areas.back') }}
        </a>
    </p>

    <div class="toolbar">
        <form method="GET" action="{{ route('admin.areas.index', $branch) }}" class="toolbar contents">
            <div class="field toolbar-grow">
                <label for="q">{{ __('interface.areas.search') }}</label>
                <input id="q" name="q" type="search" class="input" value="{{ $search }}">
            </div>

            <button type="submit" class="btn btn-ghost">{{ __('interface.areas.apply') }}</button>
        </form>

        <a href="{{ route('admin.areas.create', $branch) }}" class="btn btn-primary ms-auto">
            {{ __('interface.areas.new') }}
        </a>
    </div>

    @if ($areas->isEmpty())
        <div class="card card-pad">
            @if ($search !== '')
                <x-empty-state :title="__('interface.areas.empty_search_title')"
                               :help="__('interface.areas.empty_search_help')">
                    <a href="{{ route('admin.areas.index', $branch) }}" class="btn btn-ghost">
                        {{ __('interface.areas.clear_filters') }}
                    </a>
                </x-empty-state>
            @else
                <x-empty-state :title="__('interface.areas.empty_title')"
                               :help="__('interface.areas.empty_help')">
                    <a href="{{ route('admin.areas.create', $branch) }}" class="btn btn-primary">
                        {{ __('interface.areas.new') }}
                    </a>
                </x-empty-state>
            @endif
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <caption class="ps-3.5 pt-3">{{ __('interface.areas.caption') }}</caption>

                    <thead>
                        <tr>
                            <th scope="col">{{ __('interface.areas.name') }}</th>
                            <th scope="col">{{ __('interface.areas.code') }}</th>
                            <th scope="col">{{ __('interface.areas.status') }}</th>
                            <th scope="col">{{ __('interface.areas.people') }}</th>
                            <th scope="col">{{ __('interface.areas.evaluable') }}</th>
                            <th scope="col">{{ __('interface.areas.actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($areas as $area)
                            <tr>
                                <td>{{ $area->name }}</td>
                                <td class="table-numeric">{{ $area->code }}</td>

                                <td>
                                    @if ($area->isActive())
                                        <span class="badge badge-active">
                                            {{ __('interface.branches.state_active') }}
                                        </span>
                                    @else
                                        <span class="badge badge-archived">
                                            {{ __('interface.branches.state_archived') }}
                                        </span>
                                    @endif
                                </td>

                                <td class="table-numeric">{{ $area->memberships_count }}</td>
                                <td class="table-numeric">{{ $area->staff_members_count }}</td>

                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('admin.areas.edit', [$branch, $area]) }}"
                                           class="text-primary text-sm">
                                            {{ __('interface.areas.edit') }}
                                        </a>

                                        @if ($area->isActive())
                                            <form method="POST"
                                                  action="{{ route('admin.areas.archive', [$branch, $area]) }}">
                                                @csrf
                                                <button type="submit" class="text-ink-muted text-sm">
                                                    {{ __('interface.areas.archive') }}
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST"
                                                  action="{{ route('admin.areas.activate', [$branch, $area]) }}">
                                                @csrf
                                                <button type="submit" class="text-primary text-sm">
                                                    {{ __('interface.areas.activate') }}
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

        {{ $areas->links() }}
    @endif
</x-layouts.admin>
