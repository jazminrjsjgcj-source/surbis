<x-layouts.admin :title="__('interface.settings.title')" :subtitle="$survey->name">

    {{-- Se dice sobre que se esta escribiendo. Sin esto, quien edita la
         configuracion de una encuesta ya publicada creeria que esta cambiando
         lo que la gente esta contestando ahora mismo. RF-AO-SUR-007. --}}
    <div class="alert alert-neutral mb-4" role="status">
        {{ __('interface.settings.draft_notice', ['version' => $version->version_number]) }}
    </div>

    <form method="POST" action="{{ route('admin.surveys.settings.update', $survey) }}">
        @csrf
        @method('PUT')

        <div class="card card-pad max-w-140">
            <fieldset class="border-0 p-0">
                <legend class="text-lg">{{ __('interface.settings.identity_mode') }}</legend>

                <div class="mt-3 flex flex-col gap-2">
                    @foreach (['anonymous', 'confidential', 'optional', 'identified'] as $modo)
                        <label class="choice">
                            <input type="radio" name="identity_mode" value="{{ $modo }}"
                                   @checked(old('identity_mode', $settings->identityMode->value) === $modo)>
                            <span>
                                {{ __('interface.settings.identity_'.$modo) }}

                                {{-- Los dos con consecuencias sobre datos
                                     personales llevan explicacion; los otros
                                     dos se entienden solos. --}}
                                @if (in_array($modo, ['anonymous', 'confidential'], true))
                                    <span class="hint block">
                                        {{ __('interface.settings.identity_'.$modo.'_help') }}
                                    </span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div class="card card-pad mt-4 max-w-140">
            <div class="field">
                <label for="comment_mode">{{ __('interface.settings.comment_mode') }}</label>
                <select id="comment_mode" name="comment_mode" class="input">
                    @foreach (['disabled', 'optional', 'required'] as $modo)
                        <option value="{{ $modo }}"
                                @selected(old('comment_mode', $settings->commentMode->value) === $modo)>
                            {{ __('interface.settings.comment_'.$modo) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="inactivity_seconds">{{ __('interface.settings.inactivity') }}</label>
                <input id="inactivity_seconds" name="inactivity_seconds" type="number" class="input"
                       min="{{ \App\Domain\Surveys\VersionSettings::MIN_INACTIVITY_SECONDS }}"
                       max="{{ \App\Domain\Surveys\VersionSettings::MAX_INACTIVITY_SECONDS }}"
                       value="{{ old('inactivity_seconds', $settings->inactivitySeconds) }}"
                       required
                       aria-describedby="inactivity-hint @error('inactivity_seconds') inactivity-error @enderror"
                       @error('inactivity_seconds') aria-invalid="true" @enderror>
                <span id="inactivity-hint" class="hint">
                    {{ __('interface.settings.inactivity_hint', [
                        'min' => \App\Domain\Surveys\VersionSettings::MIN_INACTIVITY_SECONDS,
                        'max' => \App\Domain\Surveys\VersionSettings::MAX_INACTIVITY_SECONDS,
                    ]) }}
                </span>
                @error('inactivity_seconds')
                    <span id="inactivity-error" class="error">{{ $message }}</span>
                @enderror
            </div>

            <label class="text-ink-muted flex items-center gap-2 text-sm">
                <input type="checkbox" name="allow_back" value="1"
                       @checked(old('allow_back', $settings->allowBack))>
                {{ __('interface.settings.allow_back') }}
            </label>

            <label class="text-ink-muted mt-2 flex items-center gap-2 text-sm">
                <input type="checkbox" name="help_enabled" value="1"
                       @checked(old('help_enabled', $settings->helpEnabled))>
                {{ __('interface.settings.help_enabled') }}
            </label>
        </div>

        <div class="card card-pad mt-4 max-w-140">
            <div class="field">
                <label for="introduction">{{ __('interface.settings.introduction') }}</label>
                <textarea id="introduction" name="introduction" class="input h-24 py-2"
                          aria-describedby="introduction-hint">{{ old('introduction', $settings->introduction) }}</textarea>
                <span id="introduction-hint" class="hint">
                    {{ __('interface.settings.introduction_hint') }}
                </span>
            </div>

            <div class="field">
                <label for="thank_you">{{ __('interface.settings.thank_you') }}</label>
                <textarea id="thank_you" name="thank_you" class="input h-24 py-2"
                          aria-describedby="thank_you-hint">{{ old('thank_you', $settings->thankYou) }}</textarea>
                <span id="thank_you-hint" class="hint">
                    {{ __('interface.settings.thank_you_hint') }}
                </span>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('interface.settings.save') }}</button>
                <a href="{{ route('admin.surveys.edit', $survey) }}" class="btn btn-ghost">
                    {{ __('interface.surveys.cancel') }}
                </a>
            </div>
        </div>
    </form>
</x-layouts.admin>
