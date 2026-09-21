@extends('layouts.app')
@section('title', 'Drivers')
@section('heading', 'Drivers')

@push('head')
<style>
  .toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:10px; flex-wrap:wrap; }
  .toolbar p { margin:0; color:var(--muted); font-size:.9rem; }
  a.btn { text-decoration:none; display:inline-block; }
  .empty { text-align:center; color:var(--muted); padding:32px 8px; }
  .row-actions { display:flex; gap:6px; justify-content:flex-end; }
  .row-actions form { margin:0; }
  .btn.danger { color:#d13438; border-color:#d13438; } [data-theme="dark"] .btn.danger { color:#ff6b6f; border-color:#ff6b6f; }
</style>
@endpush

@section('content')
  @php $isManager = auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager'); @endphp
  <div class="card">
    <div class="toolbar">
      <p>{{ $drivers->count() }} {{ Str::plural('driver', $drivers->count()) }}</p>
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
              <td><span class="badge {{ \App\Models\Driver::badge($d->status) }}">{{ \App\Models\Driver::statusLabel($d->status) }}</span></td>
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
@endsection
