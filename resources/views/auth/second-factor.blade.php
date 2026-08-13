<x-auth-shell :title="__('interface.second_factor.title')"
              :subtitle="__('interface.second_factor.subtitle')">

    <h1 class="text-xl">{{ __('interface.second_factor.title') }}</h1>
    <p class="hint mt-1 mb-4">{{ __('interface.second_factor.help', ['email' => $email]) }}</p>

    <x-status-message />
    <x-error-summary />

    <form method="POST" action="{{ route('auth.second-factor.challenge') }}">
        @csrf

        <div class="field">
            <label for="code">{{ __('interface.second_factor.code') }}</label>
            <input id="code"
                   name="code"
                   type="text"
                   class="input input-code"
                   inputmode="numeric"
                   autocomplete="one-time-code"
                   autofocus
                   required
                   aria-describedby="code-hint @error('code') code-error @enderror"
                   @error('code') aria-invalid="true" @enderror>

            <span id="code-hint" class="hint">{{ __('interface.second_factor.code_hint') }}</span>

            @error('code')
                <span id="code-error" class="error">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">
            {{ __('interface.second_factor.submit') }}
        </button>
    </form>

    {{-- Reenviar y cancelar son formularios propios y no enlaces: cambian
         estado en el servidor, asi que van por POST con su token. --}}
    <div class="actions justify-center">
        <form method="POST" action="{{ route('auth.second-factor.resend') }}">
            @csrf
            <button type="submit" class="btn btn-ghost">
                {{ __('interface.second_factor.resend') }}
            </button>
        </form>

        {{-- RF-AUT-015: cancelar el proceso y cerrar la sesion parcial. --}}
        <form method="POST" action="{{ route('auth.second-factor.cancel') }}">
            @csrf
            <button type="submit" class="btn btn-ghost">
                {{ __('interface.second_factor.cancel') }}
            </button>
        </form>
    </div>
</x-auth-shell>
