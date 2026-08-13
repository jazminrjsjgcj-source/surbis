<x-auth-shell :title="__('interface.organizations.title')"
              :subtitle="__('interface.organizations.subtitle')">

    <h1 class="text-xl">{{ __('interface.organizations.title') }}</h1>
    <p class="hint mt-1 mb-4">{{ __('interface.organizations.help') }}</p>

    <x-error-summary />

    <form method="POST" action="{{ route('auth.organizations.choose') }}">
        @csrf

        <fieldset class="mb-4 flex flex-col gap-2 border-0 p-0">
            <legend class="sr-only">{{ __('interface.organizations.title') }}</legend>

            @foreach ($memberships as $membership)
                <label class="choice">
                    <input type="radio"
                           name="organization"
                           value="{{ $membership->organization->ulid }}"
                           required>
                    <span>{{ $membership->organization->name }}</span>
                </label>
            @endforeach
        </fieldset>

        <button type="submit" class="btn btn-primary btn-lg btn-block">
            {{ __('interface.organizations.submit') }}
        </button>
    </form>
</x-auth-shell>
