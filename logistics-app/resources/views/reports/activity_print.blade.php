<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Activity Log – {{ now()->format('Y-m-d') }}</title>
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
  td.time { white-space:nowrap; }
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
    <a href="{{ route('reports.activity', array_filter($filters)) }}">← Back</a>
    <button type="button" class="primary" onclick="window.print()">Print</button>
  </div>

  <h1>Logistics – Activity Log</h1>
  <p class="meta">
    Generated {{ now()->format('M j, Y g:i A') }} by {{ auth()->user()->name }} ({{ auth()->user()->role->label() }})<br>
    @php
      $applied = collect([
        $filters['action'] ? 'Action: '.\App\Models\ActivityLog::actionLabel($filters['action']) : null,
        $userName ? 'User: '.$userName : null,
        $filters['from'] ? 'From: '.$filters['from'] : null,
        $filters['to'] ? 'To: '.$filters['to'] : null,
        $filters['q'] !== '' ? 'Search: “'.$filters['q'].'”' : null,
      ])->filter();
    @endphp
    Filters: {{ $applied->isEmpty() ? 'none (all entries)' : $applied->join(' · ') }}<br>
    {{ number_format($total) }} {{ Str::plural('entry', $total) }}
  </p>

  @if ($truncated)
    <div class="notice">Showing the newest {{ number_format($limit) }} of {{ number_format($total) }} entries. Narrow the date range, or use Export CSV for the full list.</div>
  @endif

  <table>
    <thead><tr><th style="width:130px">Timestamp</th><th style="width:130px">User</th><th style="width:110px">Role</th><th style="width:100px">Action</th><th>Description</th></tr></thead>
    <tbody>
      @forelse ($logs as $log)
        <tr>
          <td class="time">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
          <td>{{ $log->user_name }}</td>
          <td>{{ $log->user_role ?? '—' }}</td>
          <td>{{ \App\Models\ActivityLog::actionLabel($log->action) }}</td>
          <td>{{ $log->description }}</td>
        </tr>
      @empty
        <tr><td colspan="5" style="text-align:center;padding:20px">No activity matches these filters.</td></tr>
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
