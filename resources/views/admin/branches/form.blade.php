<x-layout :title="__('interface.branches.title')">
    <main>
        <h1>{{ $branch ? __('interface.branches.edit') : __('interface.branches.new') }}</h1>

        <x-error-summary />

        <form method="POST"
              action="{{ $branch
                  ? route('admin.branches.update', $branch)
                  : route('admin.branches.store') }}">
            @csrf
            @if ($branch)
                @method('PUT')
            @endif

            <p>
                <label for="name">{{ __('interface.branches.name') }}</label>
                <input id="name"
                       name="name"
                       type="text"
                       value="{{ old('name', $branch?->name) }}"
                       required
                       @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                @error('name')
                    <span id="name-error">{{ $message }}</span>
                @enderror
            </p>

            <p>
                <label for="code">{{ __('interface.branches.code') }}</label>
                <input id="code"
                       name="code"
                       type="text"
                       value="{{ old('code', $branch?->code) }}"
                       required
                       aria-describedby="code-hint @error('code') code-error @enderror"
                       @error('code') aria-invalid="true" @enderror>
                <span id="code-hint">{{ __('interface.branches.code_hint') }}</span>
                @error('code')
                    <span id="code-error">{{ $message }}</span>
                @enderror
            </p>

            <button type="submit">{{ __('interface.branches.save') }}</button>
            <a href="{{ route('admin.branches.index') }}">{{ __('interface.branches.cancel') }}</a>
        </form>
    </main>
</x-layout>
