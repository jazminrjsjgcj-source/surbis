<x-auth-shell :title="__('interface.reset.title')" :subtitle="__('interface.reset.subtitle')">
    <h1 class="text-xl">{{ __('interface.reset.title') }}</h1>
    <p class="hint mt-1 mb-4">{{ __('interface.reset.help') }}</p>

    <x-error-summary />

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label for="email">{{ __('interface.reset.email') }}</label>
            <input id="email"
                   name="email"
                   type="email"
                   class="input"
                   value="{{ old('email', $email) }}"
                   autocomplete="username"
                   required
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')
                <span id="email-error" class="error">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label for="password">{{ __('interface.reset.password') }}</label>
            <input id="password"
                   name="password"
                   type="password"
                   class="input"
                   autocomplete="new-password"
                   required
                   aria-describedby="password-policy @error('password') password-error @enderror">

            {{-- La politica se describe ANTES de escribir, no despues de
                 fallar. El texto sale de la misma constante que la valida:
                 App\Domain\Identity\PasswordPolicy. RF-AUT-012. --}}
            <span id="password-policy" class="hint">
                {{ \App\Domain\Identity\PasswordPolicy::describe() }}
            </span>

            @error('password')
                <span id="password-error" class="error">{{ $message }}</span>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">{{ __('interface.reset.confirmation') }}</label>
            <input id="password_confirmation"
                   name="password_confirmation"
                   type="password"
                   class="input"
                   autocomplete="new-password"
                   required>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">
            {{ __('interface.reset.submit') }}
        </button>
    </form>
</x-auth-shell>
