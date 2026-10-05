<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{{-- The browser suggests the page title as the PDF's file name. --}}
<title>Shipment {{ $shipment->tracking_number }}</title>
@php
  $place = fn ($city, $province, $postal) => collect([$city, $province, $postal])->filter()->join(', ');
  $when = fn ($date) => $date?->format('M j, Y g:i A') ?? '—';
  $units = $shipment->items->sum('quantity');
  $weight = $shipment->items->sum(fn ($i) => (float) $i->unit_weight_kg * $i->quantity);
@endphp
<style>
  * { box-sizing:border-box; }
  body { margin:0 auto; padding:24px; max-width:210mm; font-family:system-ui,"Segoe UI",Arial,sans-serif; color:#111; background:#fff; font-size:12px; }
  .bar { display:flex; gap:8px; justify-content:flex-end; margin-bottom:16px; }
  .bar button, .bar a { padding:8px 14px; border:1px solid #bbb; border-radius:6px; background:#fff; color:#111; font:inherit; font-size:13px; cursor:pointer; text-decoration:none; }
  .bar .primary { background:#2454e6; border-color:#2454e6; color:#fff; }
  .head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; padding-bottom:10px; border-bottom:2px solid #111; }
  h1 { margin:0 0 4px; font-size:20px; }
  h2 { margin:18px 0 6px; font-size:12px; text-transform:uppercase; letter-spacing:.05em; }
  .meta { color:#555; margin:0; line-height:1.5; }
  .status { padding:4px 12px; border:1px solid #111; border-radius:99px; font-weight:700; white-space:nowrap; }
  .ends { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:14px; }
  .end { border:1px solid #ccc; padding:10px 12px; line-height:1.5; }
  .end small { display:block; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#555; }
  .end strong { font-size:14px; }
  .facts { display:grid; grid-template-columns:repeat(4, 1fr); gap:10px 12px; margin:14px 0 0; }
  .facts dt { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#555; }
  .facts dd { margin:2px 0 0; font-weight:600; }
  .notes { margin:14px 0 0; padding:8px 10px; border:1px solid #ccc; background:#f7f8fb; }
  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid #ccc; padding:6px 8px; text-align:left; vertical-align:top; }
  th { background:#f0f2f7; font-size:11px; text-transform:uppercase; letter-spacing:.03em; }
  td.num, th.num { text-align:right; white-space:nowrap; }
  tfoot td { font-weight:700; background:#f7f8fb; }
  thead { display:table-header-group; }          /* repeat the header on every printed page */
  tr { break-inside:avoid; page-break-inside:avoid; }
  .signs { display:grid; grid-template-columns:repeat(3, 1fr); gap:24px; margin-top:44px; break-inside:avoid; page-break-inside:avoid; }
  .signs div { border-top:1px solid #111; padding-top:4px; font-size:11px; color:#555; }
  .signs span { display:block; color:#111; font-weight:600; min-height:1.3em; }
  .footer { margin-top:18px; color:#555; font-size:11px; display:flex; justify-content:space-between; }
  @page { size:A4 portrait; margin:12mm; }
  @media print {
    body { padding:0; font-size:11px; }
    .bar { display:none !important; }             /* no buttons on paper */
    th, tfoot td, .notes { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  }
</style>
</head>
<body>
  <div class="bar">
    <a href="{{ route('shipments.show', $shipment) }}">Back</a>
    <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
  </div>

  <div class="head">
    <div>
      <h1>Shipment {{ $shipment->tracking_number }}</h1>
      <p class="meta">Logistics · Generated {{ now()->format('M j, Y g:i A') }} by {{ auth()->user()->name }} ({{ auth()->user()->roleLabel() }})</p>
    </div>
    <span class="status">
      {{ $shipment->statusLabel() }}@if ($shipment->delivery_result === 'on_time') · On time @elseif ($shipment->delivery_result === 'late') · Late @endif
    </span>
  </div>

  <div class="ends">
    <div class="end">
      <small>From</small>
      <strong>{{ $shipment->origin_name }}</strong><br>
      {{ $shipment->origin_address }}<br>
      {{ $place($shipment->origin_city, $shipment->origin_province, $shipment->origin_postal_code) }}
    </div>
    <div class="end">
      <small>To</small>
      <strong>{{ $shipment->destination_name }}</strong><br>
      {{ $shipment->destination_address }}<br>
      {{ $place($shipment->destination_city, $shipment->destination_province, $shipment->destination_postal_code) }}
    </div>
  </div>

  <dl class="facts">
    <div><dt>Scheduled pickup</dt><dd>{{ $when($shipment->scheduled_pickup_at) }}</dd></div>
    <div><dt>Scheduled delivery</dt><dd>{{ $when($shipment->scheduled_delivery_at) }}</dd></div>
    <div><dt>Dispatched</dt><dd>{{ $when($shipment->dispatched_at) }}</dd></div>
    <div><dt>Delivered</dt><dd>{{ $when($shipment->actual_delivery_at) }}</dd></div>
    <div><dt>Driver</dt><dd>{{ $shipment->driver?->name ?? 'Unassigned' }}</dd></div>
    <div><dt>Vehicle</dt><dd>{{ $shipment->vehicle?->plate_number ?? 'Unassigned' }}</dd></div>
    <div><dt>Load</dt><dd>{{ $shipment->items->count() }} {{ Str::plural('line', $shipment->items->count()) }} · {{ number_format($units) }} {{ Str::plural('unit', $units) }}</dd></div>
    <div><dt>Received by</dt><dd>{{ $shipment->received_by ?: '—' }}</dd></div>
  </dl>

  @if ($shipment->notes)
    <p class="notes"><strong>Instructions:</strong> {{ $shipment->notes }}</p>
  @endif

  <h2>Items</h2>
  <table>
    <thead><tr><th style="width:120px">SKU</th><th>Item</th><th class="num" style="width:90px">Quantity</th><th class="num" style="width:110px">Weight</th></tr></thead>
    <tbody>
      @forelse ($shipment->items as $item)
        <tr>
          <td>{{ $item->sku }}</td>
          <td>{{ $item->item_name }}</td>
          <td class="num">{{ number_format($item->quantity) }}</td>
          <td class="num">{{ $item->unit_weight_kg !== null ? number_format($item->unit_weight_kg * $item->quantity, 2).' kg' : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="4" style="text-align:center;padding:16px">No items on this shipment.</td></tr>
      @endforelse
    </tbody>
    @if ($shipment->items->isNotEmpty())
      <tfoot><tr><td colspan="2">Total</td><td class="num">{{ number_format($units) }}</td><td class="num">{{ $weight > 0 ? number_format($weight, 2).' kg' : '—' }}</td></tr></tfoot>
    @endif
  </table>

  @if ($shipment->allocations->isNotEmpty())
    <h2>Trucks</h2>
    <table>
      <thead><tr><th style="width:30px">#</th><th>Vehicle</th><th>Driver</th><th class="num">Load</th><th class="num">Capacity</th><th>Status</th></tr></thead>
      <tbody>
        @foreach ($shipment->allocations as $a)
          <tr>
            <td>{{ $a->sequence }}</td>
            <td>{{ $a->vehicle?->plate_number }} @if ($a->vehicle?->type) ({{ $a->vehicle->type }}) @endif</td>
            <td>{{ $a->driver?->name ?? 'Unassigned' }}</td>
            <td class="num">{{ number_format($a->allocated_weight_kg, 2) }} kg</td>
            <td class="num">{{ number_format($a->vehicle_capacity_kg, 2) }} kg</td>
            <td>{{ ucfirst($a->status) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  @if ($shipment->history->isNotEmpty())
    <h2>Tracking history</h2>
    <table>
      <thead><tr><th style="width:150px">When</th><th style="width:100px">Status</th><th>Note</th></tr></thead>
      <tbody>
        @foreach ($shipment->history as $h)
          <tr><td>{{ $h->changed_at->format('M j, Y g:i A') }}</td><td>{{ \App\Models\Shipment::label($h->status) }}</td><td>{{ $h->note }}</td></tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <div class="signs">
    <div><span></span>Released by (name &amp; signature)</div>
    <div><span>{{ $shipment->driver?->name }}</span>Driver</div>
    <div><span>{{ $shipment->received_by }}</span>Received by (name &amp; signature)</div>
  </div>

  <div class="footer"><span>Confidential – for internal use</span><span>Logistics system</span></div>

  <script>
    // Open the print dialog as soon as the page has loaded. Choose "Save as PDF" there to get a PDF.
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
  </script>
</body>
</html>
