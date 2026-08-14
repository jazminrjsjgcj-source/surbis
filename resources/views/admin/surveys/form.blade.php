<x-layouts.admin :title="$survey ? $survey->name : __('interface.surveys.new')"
                 :subtitle="$survey ? null : __('interface.surveys.subtitle')">

    <div class="card card-pad max-w-140">
        <form method="POST"
              action="{{ $survey
                  ? route('admin.surveys.update', $survey)
                  : route('admin.surveys.store') }}">
            @csrf
            @if ($survey)
                @method('PUT')
            @endif

            <div class="field">
                <label for="name">{{ __('interface.surveys.name') }}</label>
                <input id="name" name="name" type="text" class="input"
                       value="{{ old('name', $survey?->name) }}" required
                       @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                @error('name')
                    <span id="name-error" class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="field">
                <label for="description">{{ __('interface.surveys.description') }}</label>
                <textarea id="description" name="description" class="input h-24 py-2"
                          aria-describedby="description-hint">{{ old('description', $survey?->description) }}</textarea>
                <span id="description-hint" class="hint">
                    {{ __('interface.surveys.description_hint') }}
                </span>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('interface.surveys.save') }}</button>
                <a href="{{ route('admin.surveys.index') }}" class="btn btn-ghost">
                    {{ __('interface.surveys.cancel') }}
                </a>
            </div>
        </form>
    </div>

    @if ($survey)
        <div class="card card-pad mt-4 max-w-140">
            <h2 class="text-lg">{{ __('interface.surveys.versions_title') }}</h2>
            <p class="hint mt-1 mb-3">{{ __('interface.surveys.versions_help') }}</p>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('interface.surveys.draft') }}</th>
                            <th scope="col">{{ __('interface.surveys.version_state') }}</th>
                            <th scope="col">{{ __('interface.surveys.version_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($survey->versions as $version)
                            <tr>
                                <td class="table-numeric">
                                    {{ __('interface.surveys.version_number', ['number' => $version->version_number]) }}
                                </td>
                                <td>
                                    @if ($version->isDraft())
                                        <span class="badge badge-archived">
                                            {{ __('interface.surveys.state_draft') }}
                                        </span>
                                    @elseif ($version->isPublished())
                                        <span class="badge badge-active">
                                            {{ __('interface.surveys.state_published') }}
                                        </span>
                                    @else
                                        <span class="badge badge-archived">
                                            {{ __('interface.surveys.state_archived') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="hint">
                                    {{ $version->published_at?->diffForHumans() ?? $version->created_at?->diffForHumans() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @unless ($survey->draft)
                {{-- RF-AO-SUR-007: los cambios posteriores a una publicacion
                     van a un borrador nuevo. --}}
                <form method="POST" action="{{ route('admin.surveys.draft', $survey) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-ghost">
                        {{ __('interface.surveys.open_draft') }}
                    </button>
                </form>
            @endunless
        </div>

        <div class="card card-pad mt-4 max-w-140">
            <p class="hint">{{ __('interface.surveys.builder_pending') }}</p>
        </div>
    @endif
</x-layouts.admin>
