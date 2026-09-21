@extends('layouts.app')
@php $editing = $vehicle->exists; @endphp
@section('title', $editing ? 'Edit vehicle' : 'Add vehicle')
@section('heading', $editing ? 'Edit vehicle' : 'Add vehicle')

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
  <form class="card" method="POST" action="{{ $editing ? route('vehicles.update', $vehicle) : route('vehicles.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
      <ul class="errors">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif

    <div class="form-grid">
      <div class="field"><label for="plate_number">Plate number</label>
        <input id="plate_number" name="plate_number" value="{{ old('plate_number', $vehicle->plate_number) }}" required maxlength="20" style="text-transform:uppercase"></div>
      <div class="field"><label for="type">Type</label>
        <select id="type" name="type" required>
          <option value="">Select…</option>
          @foreach (\App\Models\Vehicle::TYPES as $t)<option value="{{ $t }}" @selected(old('type', $vehicle->type) === $t)>{{ $t }}</option>@endforeach
        </select></div>
      <div class="field"><label for="capacity_kg">Capacity (kg)</label>
        <input type="number" step="0.01" min="0" id="capacity_kg" name="capacity_kg" value="{{ old('capacity_kg', $vehicle->capacity_kg) }}"></div>
      <div class="field"><label for="status">Status</label>
        <select id="status" name="status" required>
          @foreach (\App\Models\Vehicle::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $vehicle->status) === $s)>{{ \App\Models\Vehicle::statusLabel($s) }}</option>@endforeach
        </select></div>
    </div>

    <div class="actions">
      <button type="submit" class="btn primary">{{ $editing ? 'Save changes' : 'Add vehicle' }}</button>
      <a class="btn" href="{{ route('vehicles.index') }}">Cancel</a>
    </div>
  </form>
@endsection
