<x-layouts.admin :title="__('interface.people.invite_title')">
    <div class="card card-pad max-w-140">
        <p class="hint mb-4">{{ __('interface.people.invite_help') }}</p>

        <form method="POST" action="{{ route('admin.people.store') }}">
            @csrf

            <div class="field">
                <label for="name">{{ __('interface.people.name') }}</label>
                <input id="name" name="name" type="text" class="input"
                       value="{{ old('name') }}" required
                       @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                @error('name')
                    <span id="name-error" class="error">{{ $message }}</span>
                @enderror
            </div>

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

            <div class="field">
                <label for="branch_id">{{ __('interface.people.branch') }}</label>
                <select id="branch_id" name="branch_id" class="input">
                    <option value="">{{ __('interface.people.branch_none') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    {{ __('interface.people.invite_send') }}
                </button>
                <a href="{{ route('admin.people.index') }}" class="btn btn-ghost">
                    {{ __('interface.people.cancel') }}
                </a>
            </div>
        </form>
    </div>
</x-layouts.admin>
