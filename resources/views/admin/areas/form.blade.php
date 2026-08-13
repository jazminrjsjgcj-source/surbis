<x-layouts.admin :title="$area ? __('interface.areas.edit_title') : __('interface.areas.new')"
                 :subtitle="$branch->name">

    <div class="card card-pad max-w-140">
        <form method="POST"
              action="{{ $area
                  ? route('admin.areas.update', [$branch, $area])
                  : route('admin.areas.store', $branch) }}">
            @csrf
            @if ($area)
                @method('PUT')
            @endif

            <div class="field">
                <label for="name">{{ __('interface.areas.name') }}</label>
                <input id="name"
                       name="name"
                       type="text"
                       class="input"
                       value="{{ old('name', $area?->name) }}"
                       required
                       @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                @error('name')
                    <span id="name-error" class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="field">
                <label for="code">{{ __('interface.areas.code') }}</label>
                <input id="code"
                       name="code"
                       type="text"
                       class="input"
                       value="{{ old('code', $area?->code) }}"
                       required
                       aria-describedby="code-hint @error('code') code-error @enderror"
                       @error('code') aria-invalid="true" @enderror>
                <span id="code-hint" class="hint">{{ __('interface.areas.code_hint') }}</span>
                @error('code')
                    <span id="code-error" class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('interface.areas.save') }}</button>
                <a href="{{ route('admin.areas.index', $branch) }}" class="btn btn-ghost">
                    {{ __('interface.areas.cancel') }}
                </a>
            </div>
        </form>
    </div>
</x-layouts.admin>
