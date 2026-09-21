@extends('layouts.app')
@php $editing = $driver->exists; @endphp
@section('title', $editing ? 'Edit driver' : 'Add driver')
@section('heading', $editing ? 'Edit driver' : 'Add driver')

@push('head')
<style>
  .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px; max-width:720px; }
  .field label { display:block; font-size:.82rem; font-weight:600; margin-bottom:5px; }
  .field input, .field select { width:100%; padding:9px 10px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.92rem; }
  .field input:focus, .field select:focus { outline:2px solid var(--primary); outline-offset:1px; }
  .errors { color:#d13438; font-size:.85rem; margin:0 0 14px; padding-left:18px; } [data-theme="dark"] .errors { color:#ff6b6f; }
  .actions { display:flex; gap:10px; margin-top:20px; }
  a.btn { text-decoration:none; display:inline-block; }
</style>
@endpush

@section('content')
  <form class="card" method="POST" action="{{ $editing ? route('drivers.update', $driver) : route('drivers.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
      <ul class="errors">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif

    <div class="form-grid">
      <div class="field"><label for="name">Full name</label>
        <input id="name" name="name" value="{{ old('name', $driver->name) }}" required maxlength="255"></div>
      <div class="field"><label for="phone">Phone</label>
        <input id="phone" name="phone" value="{{ old('phone', $driver->phone) }}" maxlength="30" inputmode="tel"></div>
      <div class="field"><label for="license_number">License number</label>
        <input id="license_number" name="license_number" value="{{ old('license_number', $driver->license_number) }}" required maxlength="50"></div>
      <div class="field"><label for="vehicle_id">Assigned vehicle</label>
        <select id="vehicle_id" name="vehicle_id">
          <option value="">None</option>
          @foreach ($vehicles as $v)<option value="{{ $v->id }}" @selected((string) old('vehicle_id', $driver->vehicle_id) === (string) $v->id)>{{ $v->plate_number }} · {{ $v->type }}</option>@endforeach
        </select></div>
      <div class="field"><label for="status">Status</label>
        <select id="status" name="status" required>
          @foreach (\App\Models\Driver::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $driver->status) === $s)>{{ \App\Models\Driver::statusLabel($s) }}</option>@endforeach
        </select></div>
    </div>

    <div class="actions">
      <button type="submit" class="btn primary">{{ $editing ? 'Save changes' : 'Add driver' }}</button>
      <a class="btn" href="{{ route('drivers.index') }}">Cancel</a>
    </div>
  </form>
@endsection
