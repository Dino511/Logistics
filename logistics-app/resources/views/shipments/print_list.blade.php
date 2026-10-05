<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{{-- The browser suggests the page title as the PDF's file name. --}}
<title>Shipments – {{ now()->format('Y-m-d') }}</title>
<style>
  * { box-sizing:border-box; }
  body { margin:0; padding:24px; font-family:system-ui,"Segoe UI",Arial,sans-serif; color:#111; background:#fff; font-size:12px; }
  .bar { display:flex; gap:8px; justify-content:flex-end; margin-bottom:16px; }
  .bar button, .bar a { padding:8px 14px; border:1px solid #bbb; border-radius:6px; background:#fff; color:#111; font:inherit; font-size:13px; cursor:pointer; text-decoration:none; }
  .bar .primary { background:#2454e6; border-color:#2454e6; color:#fff; }
  h1 { margin:0 0 4px; font-size:20px; }
  .meta { color:#555; margin:0 0 14px; line-height:1.5; }
  .notice { border:1px solid #d98a00; background:#fff6e0; padding:8px 10px; margin-bottom:12px; }
  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid #ccc; padding:6px 8px; text-align:left; vertical-align:top; }
  th { background:#f0f2f7; font-size:11px; text-transform:uppercase; letter-spacing:.03em; }
  td.nowrap { white-space:nowrap; }
  thead { display:table-header-group; }          /* repeat the header on every printed page */
  tr { break-inside:avoid; page-break-inside:avoid; }
  .footer { margin-top:14px; color:#555; font-size:11px; display:flex; justify-content:space-between; }
  @page { size:A4 landscape; margin:12mm; }
  @media print {
    body { padding:0; font-size:10.5px; }
    .bar { display:none !important; }             /* no buttons on paper */
    th { background:#eee !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  }
</style>
</head>
<body>
  <div class="bar">
    <a href="{{ route('shipments.index', array_filter(['q' => $search, 'status' => $status])) }}">Back</a>
    <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
  </div>

  <h1>Logistics – Shipments</h1>
  <p class="meta">
    Generated {{ now()->format('M j, Y g:i A') }} by {{ auth()->user()->name }} ({{ auth()->user()->roleLabel() }})<br>
    @php
      $applied = collect([
        $status ? 'Status: '.\App\Models\Shipment::label($status) : null,
        $search !== '' ? 'Search: “'.$search.'”' : null,
      ])->filter();
    @endphp
    Filters: {{ $applied->isEmpty() ? 'none (all shipments)' : $applied->join(' · ') }}<br>
    {{ number_format($total) }} {{ Str::plural('shipment', $total) }}
  </p>

  @if ($total > $limit)
    <div class="notice">Showing the newest {{ number_format($limit) }} of {{ number_format($total) }} shipments. Narrow the search or status filter to print the rest.</div>
  @endif

  <table>
    <thead><tr><th>Tracking no.</th><th>Origin</th><th>Destination</th><th>Items</th><th>Status</th><th>Scheduled</th><th>Delivered</th><th>Result</th></tr></thead>
    <tbody>
      @forelse ($shipments as $sh)
        <tr>
          <td class="nowrap">{{ $sh->tracking_number }}</td>
          <td>{{ $sh->origin_city }}</td>
          <td>{{ $sh->destination_name }} · {{ $sh->destination_city }}</td>
          <td class="nowrap">{{ $sh->items_count }} ({{ number_format((int) $sh->items_sum_quantity) }} units)</td>
          <td class="nowrap">{{ $sh->statusLabel() }}</td>
          <td class="nowrap">{{ $sh->scheduled_delivery_at?->format('M j, Y g:i A') ?? '—' }}</td>
          <td class="nowrap">{{ $sh->actual_delivery_at?->format('M j, Y g:i A') ?? '—' }}</td>
          <td class="nowrap">{{ ['on_time' => 'On time', 'late' => 'Late'][$sh->delivery_result] ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="8" style="text-align:center;padding:20px">No shipments match these filters.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="footer"><span>Confidential – for internal use</span><span>Logistics system</span></div>

  <script>
    // Open the print dialog as soon as the page has loaded. Choose "Save as PDF" there to get a PDF.
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
  </script>
</body>
</html>
