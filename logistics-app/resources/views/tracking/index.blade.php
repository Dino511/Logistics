@extends('layouts.app')
@section('title', 'Live tracking')
@section('heading', 'Live tracking')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
  #fleet-map { height:460px; border-radius:10px; }
  .tracking-note { color:var(--muted); font-size:.85rem; margin:0 0 14px; }
  .tracking-legend { display:flex; gap:16px; flex-wrap:wrap; font-size:.8rem; color:var(--muted); margin-top:8px; }
  .tracking-legend i { display:inline-block; width:12px; height:12px; border-radius:50%; vertical-align:middle; margin-right:5px; border:2px solid #fff; box-shadow:0 0 0 1px var(--border); }
  .empty { text-align:center; color:var(--muted); padding:28px 8px; }
  td a.track { color:var(--primary); text-decoration:none; font-weight:600; }
  #fleet-map .leaflet-popup-content a { color:#2454e6; }
</style>
@endpush

@section('content')
  <div class="card" style="margin-bottom:24px">
    <p class="tracking-note">Vehicles whose drivers are sharing their location during a delivery. Updates every minute. <span id="refreshed"></span></p>
    <div id="fleet-map"></div>
    <div class="tracking-legend">
      <span><i style="background:#16a34a"></i>Live (last {{ \App\Models\VehicleLocationPing::LIVE_MINUTES }} min)</span>
      <span><i style="background:#94a3b8"></i>Last known position</span>
    </div>
  </div>

  <div class="card">
    <h2>On the road</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Vehicle</th><th>Driver</th><th>Shipment</th><th>Deliver to</th><th>Status</th><th>Last seen</th></tr></thead>
        <tbody id="fleet-rows"></tbody>
      </table>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  (function () {
    const endpoint = @json(route('tracking.positions'));
    let positions = @json($positions);

    const map = L.map('fleet-map').setView([12.8797, 121.774], 6); // the Philippines
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
    const layer = L.layerGroup().addTo(map);
    let fittedOnce = false;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function render() {
      layer.clearLayers();
      const rows = document.getElementById('fleet-rows');
      if (!positions.length) {
        rows.innerHTML = '<tr><td colspan="6" class="empty">No vehicles are sharing their location right now.</td></tr>';
        return;
      }
      const bounds = L.latLngBounds([]);
      positions.forEach((p) => {
        bounds.extend([p.lat, p.lng]);
        L.circleMarker([p.lat, p.lng], { radius: 10, color: '#fff', weight: 3, fillColor: p.live ? '#16a34a' : '#94a3b8', fillOpacity: 1 })
          .addTo(layer)
          .bindTooltip('🚚 ' + esc(p.plate || p.driver || 'Vehicle'), { permanent: true, direction: 'right', offset: [10, 0] })
          .bindPopup('<a href="' + esc(p.url) + '"><strong>' + esc(p.tracking_number) + '</strong></a><br>'
            + esc(p.driver) + (p.plate ? ' · ' + esc(p.plate) : '') + '<br>To ' + esc(p.destination) + '<br>' + esc(p.status) + ' · ' + esc(p.ago)
            + (p.pickups ? '<br>' + esc(p.pickups) : ''));
      });
      rows.innerHTML = positions.map((p) => '<tr>'
        + '<td>' + esc(p.plate || '—') + '</td><td>' + esc(p.driver) + (p.tel ? '<br><a class="track" href="' + esc(p.tel) + '">📞 ' + esc(p.phone) + '</a>' : '') + '</td>'
        + '<td><a class="track" href="' + esc(p.url) + '">' + esc(p.tracking_number) + '</a></td>'
        + '<td>' + esc(p.destination) + '</td><td>' + esc(p.status) + (p.pickups ? '<br><small style="color:var(--muted)">' + esc(p.pickups) + '</small>' : '') + '</td>'
        + '<td><span class="badge ' + (p.live ? 'b-delivered' : 'b-pending') + '">' + esc(p.ago) + '</span></td></tr>').join('');
      if (!fittedOnce) { map.fitBounds(bounds, { padding: [60, 60], maxZoom: 14 }); fittedOnce = true; }
    }

    async function refresh() {
      try {
        const res = await fetch(endpoint, { headers: { Accept: 'application/json' } });
        if (res.ok) positions = await res.json();
        document.getElementById('refreshed').textContent = 'Last refreshed ' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) + '.';
      } catch (e) { /* offline: keep showing the last data */ }
      render();
    }

    render();
    setInterval(refresh, 60000);
  })();
</script>
@endpush
