@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
@endpush
@section('content')
    <div class="grid">
      @foreach ($stats as $s)
        <div class="card stat">
          <div class="label">{{ $s['label'] }}</div>
          <div class="value">{{ $s['value'] }}</div>
          <div class="{{ $s['up'] ? 'up' : 'down' }}">{{ $s['change'] }}</div>
        </div>
      @endforeach
    </div>

    @if ($inventory)
      <div class="grid">
        @foreach ($inventory['stats'] as $s)
          <div class="card stat">
            <div class="label">{{ $s['label'] }}</div>
            <div class="value">{{ $s['value'] }}</div>
            <div class="{{ $s['up'] ? 'up' : 'down' }}">{{ $s['change'] }}</div>
          </div>
        @endforeach
      </div>

      @if (count($inventory['lowStock']))
        <div class="card" style="margin-bottom:24px">
          <h2>Inventory needs attention</h2>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Product</th><th>SKU</th><th>Location</th><th>In stock</th><th>Reorder point</th></tr></thead>
              <tbody>
                @foreach ($inventory['lowStock'] as $row)
                  <tr>
                    <td><strong>{{ $row['product'] }}</strong></td>
                    <td>{{ $row['code'] }}</td>
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
    @endif

    <div class="card" style="margin-bottom:24px">
      <h2>Deliveries this week</h2>
      <canvas id="chart" height="90"></canvas>
    </div>

    <div class="card">
      <h2>Recent shipments</h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Origin</th><th>Destination</th><th>Driver</th><th>Status</th><th>ETA</th></tr></thead>
          <tbody>
            @forelse ($shipments as $sh)
              <tr>
                <td><a href="{{ $sh['url'] }}" style="color:var(--primary);text-decoration:none"><strong>{{ $sh['id'] }}</strong></a></td>
                <td>{{ $sh['origin'] }}</td>
                <td>{{ $sh['destination'] }}</td>
                <td>{{ $sh['driver'] }}</td>
                <td><span class="badge b-{{ $sh['class'] }}">{{ $sh['status'] }}</span></td>
                <td>{{ $sh['eta'] }}</td>
              </tr>
            @empty
              <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">No shipments yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
@endsection
@push('scripts')
<script>
  window.deliveryChart = new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
      labels: @json($weekly['labels']),
      datasets: [{ label: 'Delivered', data: @json($weekly['delivered']), backgroundColor: '#2454e6', borderRadius: 6 }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: {} } } }
  });
</script>
@endpush
