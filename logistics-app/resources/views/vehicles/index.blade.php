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
  .lease-alert { margin-bottom:16px; padding:12px 16px; border:1px solid #d13438; border-left-width:4px; border-radius:10px; background:#fde3e4; color:#7a1113; font-size:.9rem; }
  .lease-alert { position:relative; padding-right:44px; } .lease-alert[hidden] { display:none; }
  .lease-alert-close { position:absolute; top:6px; right:8px; width:30px; height:30px; border:0; border-radius:8px; background:transparent; color:inherit; font-size:1.4rem; line-height:1; cursor:pointer; }
  .lease-alert-close:hover, .lease-alert-close:focus-visible { background:rgba(209,52,56,.18); }
  .lease-alert ul { margin:6px 0; padding-left:20px; } .lease-alert a { color:inherit; font-weight:700; }
  [data-theme="dark"] .lease-alert { background:#3a1416; color:#ffd7d8; border-color:#ff6b6f; }
  .lease-note { display:block; margin-top:4px; font-size:.78rem; font-weight:600; color:#d13438; } [data-theme="dark"] .lease-note { color:#ff8a8d; }
  .btn.danger { color:#d13438; border-color:#d13438; } [data-theme="dark"] .btn.danger { color:#ff6b6f; border-color:#ff6b6f; }
</style>
@endpush

@section('content')
  @php
    $isManager = auth()->user()->hasRole('manager');
    $ended = $vehicles->filter->leaseEnded();
  @endphp
  {{-- Leased vehicles whose contract is over: they can't be used until the contract is renewed. --}}
  @if ($ended->isNotEmpty())
    <div class="lease-alert" id="leaseAlert" role="alert" data-key="{{ $ended->pluck('id')->sort()->implode('-') }}">
      <button type="button" class="lease-alert-close" id="leaseAlertClose" aria-label="Close this message" title="Close">&times;</button>
      <strong>{{ $ended->count() === 1 ? 'A leased vehicle has ended its contract' : $ended->count().' leased vehicles have ended their contracts' }}</strong>
      <ul>
        @foreach ($ended as $v)
          <li><a href="{{ route('vehicles.edit', $v) }}">{{ $v->plate_number }}</a> ({{ $v->type }}): {{ lcfirst($v->leaseEndedNote()) }}{{ $v->lease->lessor_name ? ', lessor '.$v->lease->lessor_name : '' }}.</li>
        @endforeach
      </ul>
      <span>{{ $ended->count() === 1 ? 'It is' : 'They are' }} unavailable for shipments until the contract is renewed. Open the vehicle and set a new lease end date.</span>
    </div>
  @endif
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
              {{-- A vehicle on an unfinished shipment isn't available, whatever its saved status says. --}}
              @php $on = $busy['vehicles'][$v->id] ?? null; @endphp
              <td>
                @if ($v->leaseEnded())
                  <span class="badge b-delayed">Unavailable</span>
                  <span class="lease-note">{{ $v->leaseEndedNote() }}</span>
                @elseif ($on && $v->status === 'available')
                  <span class="badge {{ \App\Models\Vehicle::badge('on_road') }}">{{ \App\Models\Vehicle::statusLabel('on_road') }}</span>
                @else
                  <span class="badge {{ \App\Models\Vehicle::badge($v->status) }}">{{ \App\Models\Vehicle::statusLabel($v->status) }}</span>
                @endif
                @if ($on)
                  <br><a class="in-use" href="{{ route('shipments.show', $on) }}">{{ $on->tracking_number }}</a>
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

@push('scripts')
<script>
  // Closing the lease warning hides it for the rest of this browser session. It comes back
  // when a different set of vehicles has an ended contract.
  (() => {
    const box = document.getElementById('leaseAlert');
    if (!box) return;
    const key = 'leaseAlertClosed';
    const read = () => { try { return sessionStorage.getItem(key); } catch (e) { return null; } };
    if (read() === box.dataset.key) box.hidden = true;
    document.getElementById('leaseAlertClose').addEventListener('click', () => {
      box.hidden = true;
      try { sessionStorage.setItem(key, box.dataset.key); } catch (e) {}
    });
  })();
</script>
@endpush
