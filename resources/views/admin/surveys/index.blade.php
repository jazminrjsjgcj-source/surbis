<x-layouts.admin :title="__('interface.surveys.title')"
                 :subtitle="__('interface.surveys.subtitle')">

    <div class="toolbar">
        <form method="GET" action="{{ route('admin.surveys.index') }}" class="toolbar contents">
            <div class="field toolbar-grow">
                <label for="q">{{ __('interface.surveys.search') }}</label>
                <input id="q" name="q" type="search" class="input" value="{{ $search }}">
            </div>

            <div class="field">
                <label for="status">{{ __('interface.surveys.status') }}</label>
                <select id="status" name="status" class="input">
                    <option value="">{{ __('interface.surveys.filter_all') }}</option>
                    <option value="draft" @selected($status === 'draft')>
                        {{ __('interface.surveys.filter_draft') }}
                    </option>
                    <option value="published" @selected($status === 'published')>
                        {{ __('interface.surveys.filter_published') }}
                    </option>
                    <option value="archived" @selected($status === 'archived')>
                        {{ __('interface.surveys.filter_archived') }}
                    </option>
                </select>
            </div>

            <button type="submit" class="btn btn-ghost">{{ __('interface.surveys.apply') }}</button>
        </form>

        <a href="{{ route('admin.surveys.create') }}" class="btn btn-primary ms-auto">
            {{ __('interface.surveys.new') }}
        </a>
    </div>

    @if ($surveys->isEmpty())
        <div class="card card-pad">
            @if ($search !== '' || $status !== '')
                <x-empty-state :title="__('interface.surveys.empty_search_title')"
                               :help="__('interface.surveys.empty_search_help')">
                    <a href="{{ route('admin.surveys.index') }}" class="btn btn-ghost">
                        {{ __('interface.surveys.clear_filters') }}
                    </a>
                </x-empty-state>
            @else
                <x-empty-state :title="__('interface.surveys.empty_title')"
                               :help="__('interface.surveys.empty_help')">
                    <a href="{{ route('admin.surveys.create') }}" class="btn btn-primary">
                        {{ __('interface.surveys.new') }}
                    </a>
                </x-empty-state>
            @endif
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <caption class="ps-3.5 pt-3">{{ __('interface.surveys.caption') }}</caption>

                    <thead>
                        <tr>
                            <th scope="col">{{ __('interface.surveys.name') }}</th>
                            <th scope="col">{{ __('interface.surveys.status') }}</th>
                            <th scope="col">{{ __('interface.surveys.published_version') }}</th>
                            <th scope="col">{{ __('interface.surveys.draft') }}</th>
                            <th scope="col">{{ __('interface.surveys.updated') }}</th>
                            <th scope="col">{{ __('interface.surveys.actions') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($surveys as $survey)
                            <tr>
                                <td>
                                    {{ $survey->name }}
                                    @if ($survey->description)
                                        <span class="hint block">{{ Str::limit($survey->description, 70) }}</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($survey->isArchived())
                                        <span class="badge badge-archived">
                                            {{ __('interface.surveys.state_archived') }}
                                        </span>
                                    @elseif ($survey->isDraft())
                                        <span class="badge badge-archived">
                                            {{ __('interface.surveys.state_draft') }}
                                        </span>
                                    @else
                                        <span class="badge badge-active">
                                            {{ __('interface.surveys.state_published') }}
                                        </span>
                                    @endif
                                </td>

                                <td class="table-numeric">
                                    {{-- Se dice que no hay, en lugar de dejar
                                         la celda vacia. --}}
                                    @if ($survey->publishedVersion)
                                        {{ __('interface.surveys.version_number', [
                                            'number' => $survey->publishedVersion->version_number,
                                        ]) }}
                                    @else
                                        <span class="hint">{{ __('interface.surveys.none_published') }}</span>
                                    @endif
                                </td>

                                <td class="table-numeric">
                                    @if ($survey->draft)
                                        {{ __('interface.surveys.version_number', [
                                            'number' => $survey->draft->version_number,
                                        ]) }}
                                    @else
                                        <span class="hint">{{ __('interface.surveys.no_draft') }}</span>
                                    @endif
                                </td>

                                <td class="hint">{{ $survey->updated_at?->diffForHumans() }}</td>

                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('admin.surveys.edit', $survey) }}"
                                           class="text-primary text-sm">
                                            {{ __('interface.surveys.edit') }}
                                        </a>

                                        @if ($survey->isArchived())
                                            <form method="POST" action="{{ route('admin.surveys.activate', $survey) }}">
                                                @csrf
                                                <button type="submit" class="text-primary text-sm">
                                                    {{ __('interface.surveys.activate') }}
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.surveys.archive', $survey) }}">
                                                @csrf
                                                <button type="submit" class="text-ink-muted text-sm">
                                                    {{ __('interface.surveys.archive') }}
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

        {{ $surveys->links() }}
    @endif
</x-layouts.admin>
