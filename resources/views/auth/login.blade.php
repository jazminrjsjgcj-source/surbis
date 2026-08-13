<x-auth-shell :title="__('interface.login.title')" :subtitle="__('interface.login.subtitle')">
    <h1 class="text-xl">{{ __('interface.login.heading') }}</h1>
    <p class="hint mt-1 mb-4">{{ __('interface.login.help') }}</p>

    <x-error-summary />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="field">
            <label for="email">{{ __('interface.login.email') }}</label>
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

        <div class="field">
            <label for="password">{{ __('interface.login.password') }}</label>
            <input id="password"
                   name="password"
                   type="password"
                   class="input"
                   autocomplete="current-password"
                   required>
        </div>

        {{-- flex-wrap: a 320 px los elementos se apilan en lugar de
             desbordar. RNF-GEN-007. --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <label for="remember" class="text-ink-muted flex items-center gap-2 text-sm">
                <input id="remember" name="remember" type="checkbox" value="1">
                {{ __('interface.login.remember') }}
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">
            {{ __('interface.login.submit') }}
        </button>
    </form>
</x-auth-shell>
