<x-layout :title="__('interface.security.title')">
    <main>
        <h1>{{ __('interface.security.title') }}</h1>

        <x-status-message />
        <x-error-summary />

        <section>
            <h2>{{ __('interface.security.mfa_heading') }}</h2>

            @if ($user->hasMfaEnabled())
                <p>{{ __('interface.security.mfa_on') }}</p>

                <form method="POST" action="{{ route('account.security.disable') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit">{{ __('interface.security.disable') }}</button>
                </form>

                <form method="POST" action="{{ route('account.security.codes') }}">
                    @csrf
                    <button type="submit">{{ __('interface.security.codes_regenerate') }}</button>
                </form>
            @else
                <p>{{ __('interface.security.mfa_off') }}</p>

                <form method="POST" action="{{ route('account.security.enable') }}">
                    @csrf
                    <button type="submit">{{ __('interface.security.enable') }}</button>
                </form>
            @endif
        </section>

        @if ($recoveryCodes)
            <section>
                <h2>{{ __('interface.security.codes_heading') }}</h2>
                <p>{{ __('interface.security.codes_help') }}</p>

                <ul>
                    @foreach ($recoveryCodes as $code)
                        <li><code>{{ $code }}</code></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>
</x-layout>
