@extends('layouts.app')
@php $creating = ! $user->exists; @endphp
@section('title', $creating ? 'Add user' : 'Edit user')
@section('heading', $creating ? 'Add user' : 'Edit user')

@push('head')
<style>
  .user-form { max-width:560px; }
  .user-form .field { margin-bottom:16px; }
  .user-form label { display:block; font-size:.82rem; font-weight:600; margin-bottom:5px; }
  .user-form input { width:100%; padding:9px 10px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.92rem; }
  .user-form input:focus { outline:2px solid var(--primary); outline-offset:1px; }
  .user-form .hint { display:block; margin-top:5px; color:var(--muted); font-size:.78rem; }
  .user-form .section-title { margin:24px 0 4px; font-size:1rem; }
  .user-form .errors { color:#d13438; font-size:.85rem; margin:0 0 14px; padding-left:18px; }
  [data-theme="dark"] .user-form .errors { color:#ff6b6f; }
  .user-form .actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
  .user-form .pw-row { position:relative; }
  .user-form .pw-row input { padding-right:60px; }
  .user-form .pw-toggle { position:absolute; right:6px; top:50%; transform:translateY(-50%); background:none; border:0; color:var(--muted); font-size:.8rem; cursor:pointer; padding:6px; }
</style>
@endpush

@section('content')
  <form class="card user-form" method="POST" action="{{ $creating ? route('users.store') : route('users.update', $user) }}" autocomplete="off">
    @csrf @unless ($creating) @method('PUT') @endunless

    @if ($errors->any())
      <ul class="errors">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif

    @unless ($creating)
      <h2>{{ $user->name }} <small style="font-weight:400;color:var(--muted)">· {{ $user->roleLabel() }}</small></h2>
    @endunless

    <div class="field">
      <label for="name">Name</label>
      <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255">
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255">
    </div>
    @if ($creating)
      <div class="field">
        <label for="role">Role</label>
        <select id="role" name="role" required style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;background:transparent;color:var(--text);font:inherit;font-size:.92rem">
          @foreach ($roles as $role)
            <option value="{{ $role->value }}" @selected(old('role', \App\Enums\Role::FieldPersonnel->value) === $role->value)>{{ $role->label() }}</option>
          @endforeach
        </select>
        <small class="hint">Field Personnel can then be linked to a driver from the Drivers page.</small>
      </div>
    @endif
    @if ($creating || $user->role === \App\Enums\Role::FieldPersonnel)
      <div class="field" id="positionField">
        <label for="field_position">Position</label>
        <select id="field_position" name="field_position" style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;background:transparent;color:var(--text);font:inherit;font-size:.92rem">
          <option value="" @selected(! old('field_position', $user->field_position)) disabled>Choose position…</option>
          @foreach (\App\Models\User::FIELD_POSITIONS as $value => $label)
            <option value="{{ $value }}" @selected(old('field_position', $user->field_position) === $value)>{{ $label }}</option>
          @endforeach
        </select>
        <small class="hint">What they do on the truck. Only drivers can be linked to a driver record.</small>
      </div>
    @endif

    <h3 class="section-title">{{ $creating ? 'Password' : 'Change password' }}</h3>
    <small class="hint" style="margin:0 0 12px">{{ $creating ? 'Share it with the person securely.' : 'Leave blank to keep the current password.' }} At least 8 characters, with letters and numbers.</small>
    <div class="field">
      <label for="password">{{ $creating ? 'Password' : 'New password' }}</label>
      <div class="pw-row">
        <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" @required($creating)>
        <button type="button" class="pw-toggle" data-toggle="password">Show</button>
      </div>
    </div>
    <div class="field">
      <label for="password_confirmation">{{ $creating ? 'Confirm password' : 'Confirm new password' }}</label>
      <div class="pw-row">
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" @required($creating)>
        <button type="button" class="pw-toggle" data-toggle="password_confirmation">Show</button>
      </div>
    </div>

    <div class="actions">
      <a class="btn" href="{{ route('users.index') }}" style="text-decoration:none">Cancel</a>
      <button type="submit" class="btn primary">{{ $creating ? 'Add user' : 'Save changes' }}</button>
    </div>
  </form>
@endsection

@push('scripts')
<script>
  // Adding a user: the Position question only applies to Field Personnel.
  (function () {
    const role = document.getElementById('role');
    const field = document.getElementById('positionField');
    if (!role || !field) return;
    const select = document.getElementById('field_position');
    function sync() {
      const isField = role.value === 'field_personnel';
      field.hidden = !isField;
      select.required = isField;
      select.disabled = !isField; // not sent for other roles
    }
    role.addEventListener('change', sync);
    sync();
  })();

  document.querySelectorAll('.pw-toggle').forEach((btn) => btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.toggle);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Hide' : 'Show';
  }));
</script>
@endpush
