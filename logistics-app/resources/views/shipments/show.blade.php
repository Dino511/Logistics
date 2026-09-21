@extends('layouts.app')
@section('title', $shipment->tracking_number)
@section('heading', $shipment->tracking_number)

@push('head')
<style>
  .detail-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin-bottom:24px; }
  .detail-grid h2 { margin-bottom:10px; }
  .kv { margin:0; font-size:.9rem; line-height:1.6; }
  .kv span { color:var(--muted); }
  .timeline { list-style:none; margin:0; padding:0; }
  .timeline li { padding:10px 0; border-bottom:1px solid var(--border); font-size:.9rem; display:flex; gap:12px; align-items:baseline; flex-wrap:wrap; }
  .timeline time { color:var(--muted); font-size:.8rem; margin-left:auto; }
  .status-form { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:0; }
  .status-form input[type=text] { padding:7px 10px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.85rem; min-width:200px; }
  a.btn { text-decoration:none; display:inline-block; }
</style>
@endpush

@section('content')
  @php $canManage = auth()->user()->isSuperAdmin() || auth()->user()->hasRole('manager', 'logistics_coordinator'); @endphp

  <div class="card" style="margin-bottom:24px">
    <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:center">
      <div>
        <span class="badge {{ $shipment->badgeClass() }}">{{ $shipment->statusLabel() }}</span>
        @if ($shipment->delivery_result === 'on_time') <span class="badge b-delivered">Delivered on time</span>
        @elseif ($shipment->delivery_result === 'late') <span class="badge b-delayed">Delivered late</span> @endif
      </div>
      <a class="btn sm" href="{{ route('shipments.index') }}">← All shipments</a>
    </div>

    @if ($canManage && count($shipment->allowedNextStatuses()))
      <form class="status-form" method="POST" action="{{ route('shipments.status', $shipment) }}" style="margin-top:16px">
        @csrf @method('PATCH')
        <select name="status" aria-label="New status">
          @foreach ($shipment->allowedNextStatuses() as $next)
            <option value="{{ $next }}">Mark as {{ \App\Models\Shipment::label($next) }}</option>
          @endforeach
        </select>
        <input type="text" name="note" placeholder="Note (optional)" maxlength="500">
        <button type="submit" class="btn primary sm">Update status</button>
      </form>
    @endif
  </div>

  <div class="detail-grid">
    <div class="card">
      <h2>Origin</h2>
      <p class="kv"><strong>{{ $shipment->origin_name }}</strong><br>{{ $shipment->origin_address }}<br>{{ collect([$shipment->origin_city, $shipment->origin_province, $shipment->origin_postal_code])->filter()->join(', ') }}</p>
    </div>
    <div class="card">
      <h2>Destination</h2>
      <p class="kv"><strong>{{ $shipment->destination_name }}</strong><br>{{ $shipment->destination_address }}<br>{{ collect([$shipment->destination_city, $shipment->destination_province, $shipment->destination_postal_code])->filter()->join(', ') }}</p>
    </div>
    <div class="card">
      <h2>Schedule</h2>
      <p class="kv">
        <span>Scheduled delivery:</span> {{ $shipment->scheduled_delivery_at->format('M j, Y g:i A') }}<br>
        <span>Dispatched:</span> {{ $shipment->dispatched_at?->format('M j, Y g:i A') ?? '—' }}<br>
        <span>Delivered:</span> {{ $shipment->actual_delivery_at?->format('M j, Y g:i A') ?? '—' }}<br>
        <span>Driver:</span> {{ $shipment->driver?->name ?? 'Unassigned' }}<br>
        <span>Vehicle:</span> {{ $shipment->vehicle?->plate_number ?? 'Unassigned' }}
      </p>
      @if ($shipment->notes)<p class="kv" style="margin-top:8px"><span>Notes:</span> {{ $shipment->notes }}</p>@endif
    </div>
  </div>

  <div class="card" style="margin-bottom:24px">
    <h2>Items</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>SKU</th><th>Item</th><th>Quantity</th></tr></thead>
        <tbody>
          @foreach ($shipment->items as $item)
            <tr><td>{{ $item->sku }}</td><td><strong>{{ $item->item_name }}</strong></td><td>{{ $item->quantity }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>Tracking history</h2>
    <ul class="timeline">
      @foreach ($shipment->history as $h)
        <li>
          <span class="badge {{ \App\Models\Shipment::badge($h->status) }}">{{ \App\Models\Shipment::label($h->status) }}</span>
          <span>{{ $h->note }}</span>
          <time>{{ $h->changed_at->format('M j, Y g:i A') }}</time>
        </li>
      @endforeach
    </ul>
  </div>
@endsection
