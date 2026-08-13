<x-layout :title="__('interface.organizations.title')">
    <h1>{{ __('interface.organizations.title') }}</h1>
    <p>{{ __('interface.organizations.help') }}</p>

    @if ($errors->any())
        <div role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('auth.organizations.choose') }}">
        @csrf

        <fieldset>
            <legend>{{ __('interface.organizations.title') }}</legend>

            @foreach ($memberships as $membership)
                <p>
                    <label>
                        <input type="radio"
                               name="organization"
                               value="{{ $membership->organization->ulid }}"
                               required>
                        {{ $membership->organization->name }}
                    </label>
                </p>
            @endforeach
        </fieldset>

        <button type="submit">{{ __('interface.organizations.submit') }}</button>
    </form>
</x-layout>
