<x-layouts.admin :title="__('interface.people.account_title')"
                 :subtitle="trim($person->first_name.' '.$person->last_name)">

    <div class="card card-pad max-w-140">
        {{--
            Se explica que NO se pierde el historial. Es la duda razonable de
            quien hace esto: darle cuenta a alguien que lleva medio ano
            evaluandose parece que va a duplicarlo.
        --}}
        <p class="hint mb-4">{{ __('interface.people.account_help') }}</p>

        <form method="POST" action="{{ route('admin.people.person.account.store', $person) }}">
            @csrf

            <div class="field">
                <label for="email">{{ __('interface.people.email') }}</label>
                <input id="email" name="email" type="email" class="input"
                       value="{{ old('email') }}" autocomplete="off" required
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')
                    <span id="email-error" class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="field">
                <label for="role">{{ __('interface.people.role') }}</label>
                <select id="role" name="role" class="input" required>
                    <option value="collaborator" @selected(old('role') === 'collaborator')>
                        {{ __('interface.people.role_collaborator') }}
                    </option>
                    <option value="admin" @selected(old('role') === 'admin')>
                        {{ __('interface.people.role_admin') }}
                    </option>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    {{ __('interface.people.account_send') }}
                </button>
                <a href="{{ route('admin.people.index') }}" class="btn btn-ghost">
                    {{ __('interface.people.cancel') }}
                </a>
            </div>
        </form>
    </div>
</x-layouts.admin>
