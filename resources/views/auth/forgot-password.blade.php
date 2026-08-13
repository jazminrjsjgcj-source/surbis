<x-auth-shell :title="__('interface.forgot.title')" :subtitle="__('interface.forgot.subtitle')">
    <h1 class="text-xl">{{ __('interface.forgot.title') }}</h1>
    <p class="hint mt-1 mb-4">{{ __('interface.forgot.help') }}</p>

    <x-status-message />

    <x-error-summary />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="field">
            <label for="email">{{ __('interface.forgot.email') }}</label>
            <input id="email"
                   name="email"
                   type="email"
                   class="input"
                   value="{{ old('email') }}"
                   autocomplete="username"
                   inputmode="email"
                   required
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')
                <span id="email-error" class="error">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">
            {{ __('interface.forgot.submit') }}
        </button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('login') }}" class="text-primary">{{ __('interface.forgot.back') }}</a>
    </p>
</x-auth-shell>
