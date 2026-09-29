@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<style>
  /* Toolbar: period switch and quick actions */
  .db-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
  .db-period { display:inline-flex; border:1px solid var(--border); border-radius:10px; overflow:hidden; background:var(--card); }
  .db-period a { padding:8px 14px; color:var(--muted); text-decoration:none; font-size:.85rem; font-weight:600; }
  .db-period a + a { border-left:1px solid var(--border); }
  .db-period a[aria-current="true"] { background:var(--primary); color:#fff; }
  .db-actions { display:flex; gap:8px; flex-wrap:wrap; }
  .db-actions .btn { display:inline-flex; align-items:center; gap:6px; text-decoration:none; padding:9px 14px; font-size:.88rem; }

  /* Sections */
  .db-section { margin-bottom:24px; }
  .db-section-title { display:flex; justify-content:space-between; align-items:baseline; margin:0 0 10px; }
  .db-section-title h2 { margin:0; font-size:.75rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); }
  .db-section-title a { font-size:.82rem; color:var(--primary); text-decoration:none; }

  /* KPI cards */
  .db-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:14px; }
  .db-kpi { position:relative; padding:16px 18px; border-radius:12px; border:1px solid var(--border); background:var(--card); transition:transform .15s, box-shadow .15s, border-color .15s; overflow:hidden; }
  .db-kpi:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,.12); border-color:color-mix(in srgb, var(--primary) 35%, var(--border)); }
  .db-kpi::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--kpi, var(--primary)); }
  .db-kpi.good { --kpi:#16a34a; } .db-kpi.warn { --kpi:#d13438; } .db-kpi.neutral { --kpi:var(--primary); }
  .db-kpi-top { display:flex; justify-content:space-between; align-items:center; color:var(--muted); font-size:.82rem; }
  .db-kpi-icon { width:30px; height:30px; display:grid; place-items:center; border-radius:8px; background:var(--hover); font-size:.95rem; }
  .db-kpi-value { font-size:1.9rem; font-weight:800; letter-spacing:-.02em; margin:8px 0 2px; font-variant-numeric:tabular-nums; }
  .db-kpi-sub { font-size:.8rem; color:var(--muted); }
  .db-kpi.warn .db-kpi-value, .db-kpi.warn .db-kpi-sub { color:#d13438; }
  [data-theme="dark"] .db-kpi.warn .db-kpi-value, [data-theme="dark"] .db-kpi.warn .db-kpi-sub { color:#ff6b6f; }

  /* Chart + needs attention */
  .db-split { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr); gap:16px; align-items:stretch; }
  .db-card { padding:18px 20px; border-radius:12px; border:1px solid var(--border); background:var(--card); }
  .db-card h3 { margin:0 0 14px; font-size:1rem; }
  .db-card-head { display:flex; justify-content:space-between; align-items:baseline; gap:10px; }
  .db-card-head small { color:var(--muted); }
  .db-chart { position:relative; height:260px; }
  .db-attn { list-style:none; margin:0; padding:0; }
  .db-attn li + li { border-top:1px solid var(--border); }
  .db-attn a { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:10px 2px; color:var(--text); text-decoration:none; font-size:.88rem; }
  .db-attn a:hover { background:var(--hover); }
  .db-attn small { display:block; color:var(--muted); font-size:.78rem; }
  .db-ok { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; height:calc(100% - 30px); min-height:140px; color:var(--muted); text-align:center; font-size:.9rem; }
  .db-ok span { font-size:1.8rem; }

  /* Tables */
  .db-table td, .db-table th { padding:11px 12px; }
  .db-table tbody tr { cursor:pointer; }
  .db-table tbody tr:hover { background:var(--hover); }
  .db-table a { color:var(--primary); text-decoration:none; font-weight:700; }
  .db-muted { color:var(--muted); }

  @media (max-width:980px) { .db-split { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
  @php $u = auth()->user(); @endphp

  {{-- Period switch and quick actions --}}
  <div class="db-toolbar">
    <nav class="db-period" aria-label="Period">
      <a href="{{ route('dashboard', ['range' => 7]) }}" aria-current="{{ $range === 7 ? 'true' : 'false' }}">Last 7 days</a>
      <a href="{{ route('dashboard', ['range' => 30]) }}" aria-current="{{ $range === 30 ? 'true' : 'false' }}">Last 30 days</a>
    </nav>
    <div class="db-actions">
      <a class="btn" href="{{ route('dashboard.export', ['range' => $range]) }}">⬇ Export CSV</a>
      <a class="btn" href="{{ route('tracking.index') }}">📍 Live tracking</a>
      <a class="btn primary" href="{{ route('shipments.create') }}">+ New shipment</a>
    </div>
  </div>

  {{-- Shipments at a glance --}}
  <section class="db-section">
    <div class="db-section-title"><h2>Shipments</h2><a href="{{ route('shipments.index') }}">View all →</a></div>
    <div class="db-kpis">
      @foreach ($stats as $s)
        <div class="db-kpi {{ $s['tone'] }}">
          <div class="db-kpi-top"><span>{{ $s['label'] }}</span><span class="db-kpi-icon" aria-hidden="true">{{ $s['icon'] }}</span></div>
          <div class="db-kpi-value">{{ $s['value'] }}</div>
          <div class="db-kpi-sub">{{ $s['change'] }}</div>
        </div>
      @endforeach
    </div>
  </section>

  {{-- Performance and what needs attention now --}}
  <section class="db-section db-split">
    <div class="db-card">
      <div class="db-card-head"><h3>Deliveries</h3><small>last {{ $range }} days · {{ array_sum($weekly['delivered']) }} delivered</small></div>
      <div class="db-chart"><canvas id="chart" aria-label="Deliveries per day"></canvas></div>
    </div>
    <div class="db-card">
      <div class="db-card-head"><h3>Needs attention</h3>@if ($attention->isNotEmpty())<small>{{ $attention->count() }}</small>@endif</div>
      @if ($attention->isEmpty())
        <div class="db-ok"><span>✓</span>All shipments are on track.</div>
      @else
        <ul class="db-attn">
          @foreach ($attention as $a)
            <li>
              <a href="{{ route('shipments.show', $a) }}">
                <span><strong>{{ $a->tracking_number }}</strong><small>{{ $a->destination_city }} · {{ $a->driver?->name ?? 'Unassigned' }}</small></span>
                <span style="text-align:right">
                  <span class="badge b-delayed">{{ $a->status === 'delayed' ? $a->statusLabel() : 'Overdue' }}</span>
                  <small>due {{ $a->scheduled_delivery_at->diffForHumans() }}</small>
                </span>
              </a>
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  </section>

  {{-- Inventory (hidden if the Inventory system can't be reached) --}}
  @if ($inventory)
    <section class="db-section">
      <div class="db-section-title"><h2>Inventory</h2></div>
      <div class="db-kpis">
        @foreach ($inventory['stats'] as $s)
          <div class="db-kpi {{ $s['label'] === 'Low stock items' && $s['value'] > 0 ? 'warn' : 'neutral' }}">
            <div class="db-kpi-top"><span>{{ $s['label'] }}</span></div>
            <div class="db-kpi-value">{{ $s['value'] }}</div>
            <div class="db-kpi-sub">{{ $s['change'] }}</div>
          </div>
        @endforeach
      </div>

      @if (count($inventory['lowStock']))
        <div class="db-card" style="margin-top:14px">
          <h3>Low stock</h3>
          <div class="table-wrap">
            <table class="db-table">
              <thead><tr><th>Product</th><th>SKU</th><th>Location</th><th>In stock</th><th>Reorder point</th></tr></thead>
              <tbody>
                @foreach ($inventory['lowStock'] as $row)
                  <tr style="cursor:default">
                    <td><strong>{{ $row['product'] }}</strong></td>
                    <td class="db-muted">{{ $row['code'] }}</td>
                    <td>{{ $row['location'] }}</td>
                    <td><span class="badge b-delayed">{{ $row['quantity'] }}</span></td>
                    <td>{{ $row['reorder'] }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </section>
  @endif

  {{-- Recent shipments --}}
  <section class="db-section">
    <div class="db-section-title"><h2>Recent shipments</h2><a href="{{ route('shipments.index') }}">View all →</a></div>
    <div class="db-card" style="padding:6px 8px">
      <div class="table-wrap">
        <table class="db-table">
          <thead><tr><th>Tracking no.</th><th>Route</th><th>Driver</th><th>Status</th><th>ETA</th></tr></thead>
          <tbody>
            @forelse ($shipments as $sh)
              <tr data-href="{{ $sh['url'] }}">
                <td><a href="{{ $sh['url'] }}">{{ $sh['id'] }}</a></td>
                <td>{{ $sh['origin'] }} <span class="db-muted">→</span> {{ $sh['destination'] }}</td>
                <td>{{ $sh['driver'] }}</td>
                <td><span class="badge {{ $sh['class'] }}">{{ $sh['status'] }}</span></td>
                <td class="db-muted">{{ $sh['eta'] }}</td>
              </tr>
            @empty
              <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px">No shipments yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>
@endsection

@push('scripts')
<script>
  // Whole table rows open their shipment.
  document.querySelectorAll('tr[data-href]').forEach((tr) => tr.addEventListener('click', (e) => {
    if (!e.target.closest('a')) location.href = tr.dataset.href;
  }));

  window.deliveryChart = new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
      labels: @json($weekly['labels']),
      datasets: [{ label: 'Delivered', data: @json($weekly['delivered']), backgroundColor: '#2454e6', borderRadius: 6, maxBarThickness: 42 }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: {} }, x: { grid: { display: false } } }
    }
  });
</script>
@endpush
