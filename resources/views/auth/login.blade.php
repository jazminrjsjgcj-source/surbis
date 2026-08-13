<x-layout :title="__('interface.login.title')">
    <h1>{{ __('interface.login.title') }}</h1>

    {{-- El resumen de errores va antes del formulario y con role=alert para
         que un lector de pantalla lo anuncie al recargar. RNF-AUT-004. --}}
    @if ($errors->any())
        <div role="alert">
            <p>{{ __('interface.errors.summary') }}</p>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <p>
            <label for="email">{{ __('interface.login.email') }}</label>
            <input id="email"
                   name="email"
                   type="email"
                   value="{{ old('email') }}"
                   autocomplete="username"
                   required
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')
                <span id="email-error">{{ $message }}</span>
            @enderror
        </p>

        <p>
            <label for="password">{{ __('interface.login.password') }}</label>
            <input id="password"
                   name="password"
                   type="password"
                   autocomplete="current-password"
                   required>
        </p>

        <p>
            <label for="remember">
                <input id="remember" name="remember" type="checkbox" value="1">
                {{ __('interface.login.remember') }}
            </label>
        </p>

        <button type="submit">{{ __('interface.login.submit') }}</button>
    </form>
</x-layout>
