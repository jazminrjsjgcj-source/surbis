<x-layouts.admin :title="$person ? __('interface.people.person_edit_title') : __('interface.people.person_new')"
                 :subtitle="__('interface.people.person_help')">

    <div class="card card-pad max-w-140">
        <form method="POST"
              action="{{ $person
                  ? route('admin.people.person.update', $person)
                  : route('admin.people.person.store') }}">
            @csrf
            @if ($person)
                @method('PUT')
            @endif

            <div class="field">
                <label for="first_name">{{ __('interface.people.first_name') }}</label>
                <input id="first_name" name="first_name" type="text" class="input"
                       value="{{ old('first_name', $person?->first_name) }}" required
                       @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror>
                @error('first_name')
                    <span id="first_name-error" class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="field">
                <label for="last_name">{{ __('interface.people.last_name') }}</label>
                <input id="last_name" name="last_name" type="text" class="input"
                       value="{{ old('last_name', $person?->last_name) }}" required
                       @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror>
                @error('last_name')
                    <span id="last_name-error" class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="field">
                <label for="employee_code">{{ __('interface.people.employee_code') }}</label>
                <input id="employee_code" name="employee_code" type="text" class="input"
                       value="{{ old('employee_code', $person?->employee_code) }}"
                       aria-describedby="employee_code-hint @error('employee_code') employee_code-error @enderror"
                       @error('employee_code') aria-invalid="true" @enderror>
                <span id="employee_code-hint" class="hint">
                    {{ __('interface.people.employee_code_hint') }}
                </span>
                @error('employee_code')
                    <span id="employee_code-error" class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="field">
                <label for="branch_id">{{ __('interface.people.branch') }}</label>
                <select id="branch_id" name="branch_id" class="input">
                    <option value="">{{ __('interface.people.branch_none') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}"
                                @selected(old('branch_id', $person?->branch_id) == $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('interface.people.save') }}</button>
                <a href="{{ route('admin.people.index') }}" class="btn btn-ghost">
                    {{ __('interface.people.cancel') }}
                </a>
            </div>
        </form>
    </div>
</x-layouts.admin>
