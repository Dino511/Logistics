@extends('layouts.app')
@section('title', 'Drivers')
@section('heading', 'Drivers & helpers')

@push('head')
<style>
  .toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:10px; flex-wrap:wrap; }
  .toolbar p { margin:0; color:var(--muted); font-size:.9rem; }
  a.btn { text-decoration:none; display:inline-block; }
  .empty { text-align:center; color:var(--muted); padding:32px 8px; }
  .row-actions { display:flex; gap:6px; justify-content:flex-end; }
  .row-actions form { margin:0; }
  .in-use { display:inline-block; margin-top:4px; font-size:.78rem; font-weight:600; color:#d98a00; text-decoration:none; }
  .btn.danger { color:#d13438; border-color:#d13438; } [data-theme="dark"] .btn.danger { color:#ff6b6f; border-color:#ff6b6f; }
</style>
@endpush

@section('content')
  @php $isManager = auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager'); @endphp
  <div class="card">
    <div class="toolbar">
      <div>
        <h2 style="margin:0 0 2px">Drivers</h2>
        <p>{{ $drivers->count() }} {{ Str::plural('driver', $drivers->count()) }}</p>
      </div>
      <a class="btn primary" href="{{ route('drivers.create') }}">+ Add driver</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Phone</th><th>License no.</th><th>Vehicle</th><th>Status</th><th>Shipments</th><th></th></tr></thead>
        <tbody>
          @forelse ($drivers as $d)
            <tr>
              <td><strong>{{ $d->name }}</strong></td>
              <td>{{ $d->phone ?: '—' }}</td>
              <td>{{ $d->license_number }}</td>
              <td>{{ $d->vehicle?->plate_number ?? '—' }}</td>
              <td><span class="badge {{ \App\Models\Driver::badge($d->status) }}">{{ \App\Models\Driver::statusLabel($d->status) }}</span>
                @if ($on = $busy['drivers'][$d->id] ?? null)
                  <br><a class="in-use" href="{{ route('shipments.show', $on) }}">On delivery · {{ $on->tracking_number }}</a>
                @endif
              </td>
              <td>{{ $d->shipments_count }}</td>
              <td>
                <div class="row-actions">
                  <a class="btn sm" href="{{ route('drivers.edit', $d) }}">Edit</a>
                  @if ($isManager)
                    <form method="POST" action="{{ route('drivers.destroy', $d) }}" onsubmit="return confirm('Delete driver {{ addslashes($d->name) }}?')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn sm danger">Delete</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="empty">No drivers yet. Add your first one.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  {{-- Truck / cargo helpers: the crew who ride along and handle the load. --}}
  <div class="card" id="helpers" style="margin-top:24px">
    <div class="toolbar">
      <div>
        <h2 style="margin:0 0 2px">Truck / cargo helpers</h2>
        <p>{{ $helpers->count() }} {{ Str::plural('helper', $helpers->count()) }}</p>
      </div>
      <a class="btn primary" href="{{ route('helpers.create') }}">+ Add helper</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Phone</th><th>Truck</th><th>Sign-in account</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($helpers as $h)
            <tr>
              <td><strong>{{ $h->name }}</strong></td>
              <td>{{ $h->phone ?: '—' }}</td>
              <td>{{ $h->vehicle?->plate_number ?? '—' }}</td>
              <td>{{ $h->user?->email ?? '—' }}</td>
              <td><span class="badge {{ \App\Models\Driver::badge($h->status) }}">{{ \App\Models\Driver::statusLabel($h->status) }}</span>
                @if ($on = $busy['helpers'][$h->id] ?? null)
                  <br><a class="in-use" href="{{ route('shipments.show', $on) }}">On delivery · {{ $on->tracking_number }}</a>
                @endif
              </td>
              <td>
                <div class="row-actions">
                  <a class="btn sm" href="{{ route('helpers.edit', $h) }}">Edit</a>
                  @if ($isManager)
                    <form method="POST" action="{{ route('helpers.destroy', $h) }}" onsubmit="return confirm('Delete helper {{ addslashes($h->name) }}?')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn sm danger">Delete</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="empty">No helpers yet. Add your first one.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
