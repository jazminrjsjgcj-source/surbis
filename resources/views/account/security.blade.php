<x-layout :title="__('interface.security.title')">
    <div class="grid min-h-screen place-items-start justify-center p-6">
        <div class="w-full max-w-140">
            <h1 class="mb-4 text-2xl">{{ __('interface.security.title') }}</h1>

            <x-status-message />
            <x-error-summary />

            <div class="card card-pad">
                <section class="panel">
                    <h2 class="text-lg">{{ __('interface.security.mfa_heading') }}</h2>

                    @if ($user->hasMfaEnabled())
                        {{-- El estado se nombra en texto, no solo con color.
                             ANEXO 1 seccion 47. --}}
                        <p class="hint mt-1">{{ __('interface.security.mfa_on') }}</p>

                        <div class="actions">
                            <form method="POST" action="{{ route('account.security.disable') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost">
                                    {{ __('interface.security.disable') }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('account.security.codes') }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost">
                                    {{ __('interface.security.codes_regenerate') }}
                                </button>
                            </form>
                        </div>
                    @else
                        <p class="hint mt-1">{{ __('interface.security.mfa_off') }}</p>

                        <div class="actions">
                            <form method="POST" action="{{ route('account.security.enable') }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">
                                    {{ __('interface.security.enable') }}
                                </button>
                            </form>
                        </div>
                    @endif
                </section>

                @if ($recoveryCodes)
                    <section class="panel">
                        <h2 class="text-lg">{{ __('interface.security.codes_heading') }}</h2>

                        {{-- role="alert": estos codigos se muestran UNA sola
                             vez. Si el lector de pantalla no los anuncia,
                             quien no ve la pantalla los pierde sin enterarse. --}}
                        <div class="alert alert-neutral mt-2" role="alert">
                            {{ __('interface.security.codes_help') }}
                        </div>

                        <ul class="code-list mt-3">
                            @foreach ($recoveryCodes as $code)
                                <li>{{ $code }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            <p class="mt-4 text-sm">
                <a href="{{ route('home') }}" class="text-primary">{{ __('interface.security.back') }}</a>
            </p>
        </div>
    </div>
</x-layout>
