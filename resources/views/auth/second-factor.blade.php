<x-layout :title="__('interface.second_factor.title')">
    <main>
        <h1>{{ __('interface.second_factor.title') }}</h1>
        <p>{{ __('interface.second_factor.help', ['email' => $email]) }}</p>

        <x-status-message />
        <x-error-summary />

        <form method="POST" action="{{ route('auth.second-factor.challenge') }}">
            @csrf

            <p>
                <label for="code">{{ __('interface.second_factor.code') }}</label>
                <input id="code"
                       name="code"
                       type="text"
                       inputmode="numeric"
                       autocomplete="one-time-code"
                       autofocus
                       required
                       aria-describedby="code-hint @error('code') code-error @enderror"
                       @error('code') aria-invalid="true" @enderror>
                <span id="code-hint">{{ __('interface.second_factor.code_hint') }}</span>
                @error('code')
                    <span id="code-error">{{ $message }}</span>
                @enderror
            </p>

            <button type="submit">{{ __('interface.second_factor.submit') }}</button>
        </form>

        <form method="POST" action="{{ route('auth.second-factor.resend') }}">
            @csrf
            <button type="submit">{{ __('interface.second_factor.resend') }}</button>
        </form>

        {{-- RF-AUT-015: cancelar el proceso y cerrar la sesion parcial. --}}
        <form method="POST" action="{{ route('auth.second-factor.cancel') }}">
            @csrf
            <button type="submit">{{ __('interface.second_factor.cancel') }}</button>
        </form>
    </main>
</x-layout>
