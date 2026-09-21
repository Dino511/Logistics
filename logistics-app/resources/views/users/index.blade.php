@extends('layouts.app')
@section('title', 'Users & Roles')
@section('heading', 'Users & Roles')

@section('content')
  <div class="card">
    <h2>All users</h2>
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
              </td>
              <td><span class="badge {{ $user->is_active ? 'b-active' : 'b-inactive' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
              <td>
                <form class="inline" method="POST" action="{{ route('users.active', $user) }}">
                  @csrf @method('PATCH')
                  <button type="submit" class="btn sm" @disabled($self)>{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
