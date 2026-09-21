@extends('layouts.app')
@section('title', 'Shipments')
@section('heading', 'Shipments')

@push('head')
<style>
  .toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; margin-bottom:16px; }
  .toolbar form { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:0; }
  .toolbar input[type=search] { padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.9rem; min-width:220px; }
  a.btn { text-decoration:none; display:inline-block; }
  .pager { display:flex; justify-content:space-between; margin-top:14px; }
  .empty { text-align:center; color:var(--muted); padding:32px 8px; }
  td a.track { color:var(--primary); text-decoration:none; font-weight:600; }
</style>
@endpush

@section('content')
  <div class="card">
    <div class="toolbar">
      <form method="GET" action="{{ route('shipments.index') }}">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search tracking no. or destination" aria-label="Search shipments">
        <select name="status" aria-label="Filter by status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          @foreach (\App\Models\Shipment::STATUSES as $s)
            <option value="{{ $s }}" @selected($status === $s)>{{ \App\Models\Shipment::label($s) }}</option>
          @endforeach
        </select>
        <button type="submit" class="btn sm">Search</button>
      </form>
      @if (auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager', 'logistics_coordinator'))
        <a class="btn primary" href="{{ route('shipments.create') }}">+ New shipment</a>
      @endif
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Tracking no.</th><th>Origin</th><th>Destination</th><th>Items</th><th>Status</th><th>Scheduled</th><th>Result</th></tr></thead>
        <tbody>
          @forelse ($shipments as $sh)
            <tr>
              <td><a class="track" href="{{ route('shipments.show', $sh) }}">{{ $sh->tracking_number }}</a></td>
              <td>{{ $sh->origin_city }}</td>
              <td>{{ $sh->destination_name }} · {{ $sh->destination_city }}</td>
              <td>{{ $sh->items_count }} ({{ (int) $sh->items_sum_quantity }} units)</td>
              <td><span class="badge {{ $sh->badgeClass() }}">{{ $sh->statusLabel() }}</span></td>
              <td>{{ $sh->scheduled_delivery_at->format('M j, g:i A') }}</td>
              <td>
                @if ($sh->delivery_result === 'on_time') <span class="badge b-delivered">On time</span>
                @elseif ($sh->delivery_result === 'late') <span class="badge b-delayed">Late</span>
                @else — @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="empty">No shipments found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($shipments->hasPages())
      <div class="pager">
        @if ($shipments->previousPageUrl()) <a class="btn sm" href="{{ $shipments->previousPageUrl() }}">← Previous</a> @else <span></span> @endif
        @if ($shipments->nextPageUrl()) <a class="btn sm" href="{{ $shipments->nextPageUrl() }}">Next →</a> @endif
      </div>
    @endif
  </div>
@endsection
