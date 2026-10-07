@extends('layouts.app')
@section('title', 'Fleet')
@section('heading', 'Fleet')

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
  @php $isManager = auth()->user()->hasRole('manager'); @endphp
  <div class="card">
    <div class="toolbar">
      <p>{{ $vehicles->count() }} {{ Str::plural('vehicle', $vehicles->count()) }}</p>
      <a class="btn primary" href="{{ route('vehicles.create') }}">+ Add vehicle</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Plate no.</th><th>Type</th><th>Ownership</th><th>Capacity</th><th>Status</th><th>Drivers</th><th>Shipments</th><th></th></tr></thead>
        <tbody>
          @forelse ($vehicles as $v)
            <tr>
              <td><strong>{{ $v->plate_number }}</strong></td>
              <td>{{ $v->type }}</td>
              <td>
                @if ($v->is_rented)
                  <span class="badge b-pending">Leased</span>
                  @if ($v->lease)<div style="font-size:.75rem;color:var(--muted);margin-top:3px">{{ $v->lease->rateSummary() }}</div>@endif
                @else
                  <span class="badge b-delivered">Owned</span>
                @endif
              </td>
              <td>{{ $v->capacity_kg !== null ? number_format($v->capacity_kg, 0).' kg' : '—' }}</td>
              <td><span class="badge {{ \App\Models\Vehicle::badge($v->status) }}">{{ \App\Models\Vehicle::statusLabel($v->status) }}</span>
                @if ($on = $busy['vehicles'][$v->id] ?? null)
                  <br><a class="in-use" href="{{ route('shipments.show', $on) }}">In use · {{ $on->tracking_number }}</a>
                @endif
              </td>
              <td>{{ $v->drivers_count }}</td>
              <td>{{ $v->shipments_count }}</td>
              <td>
                <div class="row-actions">
                  <a class="btn sm" href="{{ route('vehicles.edit', $v) }}">Edit</a>
                  @if ($isManager)
                    <form method="POST" action="{{ route('vehicles.destroy', $v) }}" onsubmit="return confirm('Delete vehicle {{ $v->plate_number }}?')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn sm danger">Delete</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="empty">No vehicles yet. Add your first one.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
