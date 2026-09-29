@extends('layouts.app')
@section('title', 'Users & Roles')
@section('heading', 'Users & Roles')

@push('head')
<style>
  .position-form { display:flex; margin-top:6px; }
  .position-form select.needs-position { border-color:#d97706; }
</style>
@endpush

@section('content')
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px">
      <h2 style="margin:0">All users</h2>
      <a class="btn primary" href="{{ route('users.create') }}" style="text-decoration:none">+ Add user</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach ($users as $user)
            @php $self = $user->is(auth()->user()); @endphp
            <tr>
              <td><strong>{{ $user->name }}</strong>@if ($self) <small>(you)</small>@endif</td>
              <td>{{ $user->email }}</td>
              <td>
                <form class="inline" method="POST" action="{{ route('users.role', $user) }}">
                  @csrf @method('PATCH')
                  <select name="role" onchange="this.form.submit()" @disabled($self) aria-label="Role for {{ $user->name }}">
                    @foreach ($roles as $role)
                      <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                    @endforeach
                  </select>
                </form>
                @if ($user->role === \App\Enums\Role::FieldPersonnel)
                  <form class="inline position-form" method="POST" action="{{ route('users.position', $user) }}">
                    @csrf @method('PATCH')
                    <select name="field_position" onchange="this.form.submit()" aria-label="Position for {{ $user->name }}" class="{{ $user->field_position ? '' : 'needs-position' }}">
                      @unless ($user->field_position)<option value="" selected disabled>Choose position…</option>@endunless
                      @foreach (\App\Models\User::FIELD_POSITIONS as $value => $label)
                        <option value="{{ $value }}" @selected($user->field_position === $value)>{{ $label }}</option>
                      @endforeach
                    </select>
                  </form>
                @endif
              </td>
              <td><span class="badge {{ $user->is_active ? 'b-active' : 'b-inactive' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
              <td>
                <div class="inline">
                  <a class="btn sm" href="{{ route('users.edit', $user) }}" style="text-decoration:none" aria-label="Edit {{ $user->name }}">Edit</a>
                  <form class="inline" method="POST" action="{{ route('users.active', $user) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn sm" @disabled($self)>{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
