{{--
  Route map (Leaflet + OpenStreetMap). Draws the suggested road route from origin to
  destination for each shipment, using the free OSRM routing service. If a road route
  can't be found (offline, no road connection), a dashed straight line is shown instead.

  $shipments: one Shipment or a collection of them
  $mapId:     unique element id
  $height:    CSS height (default 300px)
  $live:      optional latest shared positions: one VehicleLocationPing or a collection
             (one per truck on a split shipment)
--}}
@php
    $mapId = $mapId ?? 'route-map';
    $height = $height ?? '300px';
    $livePoints = collect(isset($live) ? ($live instanceof \App\Models\VehicleLocationPing ? [$live] : $live) : [])
        ->map(fn ($p) => $p->toMapPoint())->values();
    $routes = collect($shipments instanceof \App\Models\Shipment ? [$shipments] : $shipments)
        ->filter(fn ($s) => $s->origin_latitude !== null && $s->origin_longitude !== null
            && $s->destination_latitude !== null && $s->destination_longitude !== null)
        ->map(fn ($s) => [
            'from' => ['lat' => (float) $s->origin_latitude, 'lng' => (float) $s->origin_longitude, 'label' => $s->origin_name],
            'to' => ['lat' => (float) $s->destination_latitude, 'lng' => (float) $s->destination_longitude, 'label' => $s->destination_name],
            // Extra pickup stops (after the origin) the route passes through, in order.
            'via' => $s->relationLoaded('pickups')
                ? $s->pickups->skip(1)->filter(fn ($p) => $p->latitude !== null && $p->longitude !== null)
                    ->map(fn ($p) => ['lat' => (float) $p->latitude, 'lng' => (float) $p->longitude, 'label' => $p->sequence.' · '.$p->name])->values()->all()
                : [],
            'status' => $s->status,
            'popup' => '<a href="'.e(route('shipments.show', $s)).'"><strong>'.e($s->tracking_number).'</strong></a><br>'
                .e($s->origin_city).' to '.e($s->destination_city).'<br>'.e($s->statusLabel()),
        ])
        ->values();
@endphp

@once
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    .route-map-title { font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); margin:0 0 10px; }
    .route-map-empty { padding:28px 12px; text-align:center; color:var(--muted); border:1px dashed var(--border); border-radius:10px; font-size:.88rem; }
    .route-map-legend { display:flex; flex-wrap:wrap; gap:14px; margin-top:8px; font-size:.78rem; color:var(--muted); }
    .route-map-legend i { display:inline-block; width:18px; height:0; border-top:4px solid; vertical-align:middle; margin-right:5px; border-radius:2px; }
    .route-map-summary { margin-top:10px; font-size:.88rem; color:var(--muted); }
    .route-map-summary strong { color:var(--text); }
    .route-map .leaflet-popup-content a { color:#2454e6; }
  </style>
@endonce

<div class="card" style="margin-bottom:24px">
  <p class="route-map-title">Route map</p>

  @if ($routes->isEmpty())
    <div class="route-map-empty">{{ $emptyMessage ?? 'No map location for this route yet. It fills in automatically from the address.' }}</div>
  @else
    <div id="{{ $mapId }}" class="route-map" style="height:{{ $height }}; border-radius:10px;"></div>

    @if ($routes->count() > 1)
      <div class="route-map-legend">
        <span><i style="border-color:#f59e0b"></i>Pending</span>
        <span><i style="border-color:#2563eb"></i>In transit</span>
        <span><i style="border-color:#dc2626"></i>Delayed</span>
        <span><i style="border-color:#16a34a"></i>Delivered</span>
        <span><i style="border-color:#64748b;border-top-style:dashed"></i>No road route found (straight line)</span>
      </div>
    @else
      <div class="route-map-summary" id="{{ $mapId }}-summary">Finding the suggested road route…</div>
    @endif

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const routes = @json($routes);
        const live = @json($livePoints);
        const summary = document.getElementById(@json($mapId . '-summary'));
        const colors = { pending: '#f59e0b', in_transit: '#2563eb', delayed: '#dc2626', delivered: '#16a34a', cancelled: '#64748b' };

        const map = L.map(@json($mapId));
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap contributors · Routes &copy; OSRM',
        }).addTo(map);

        const bounds = L.latLngBounds([]);
        const seen = {};
        const addPoint = (p) => {
          bounds.extend([p.lat, p.lng]);
          const key = p.lat + ',' + p.lng;
          if (seen[key]) return;
          seen[key] = true;
          L.circleMarker([p.lat, p.lng], { radius: 7, color: '#fff', weight: 2, fillColor: '#1e3a5f', fillOpacity: 1 })
            .addTo(map)
            .bindTooltip(p.label || '', { permanent: routes.length <= 3, direction: 'top', offset: [0, -8] });
        };

        const fit = () => map.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 });

        const formatTrip = (meters, seconds) => {
          const km = meters / 1000;
          const mins = Math.round(seconds / 60);
          const time = mins >= 60 ? Math.floor(mins / 60) + ' h ' + (mins % 60) + ' min' : mins + ' min';
          return (km >= 10 ? Math.round(km) : km.toFixed(1)) + ' km · about ' + time + ' (car, no traffic)';
        };

        // Suggested road route from OSRM, remembered in the browser so the free
        // service isn't asked again for the same trip.
        async function roadRoute(r) {
          const stops = [r.from, ...r.via, r.to];
          const key = 'osrm:' + stops.flatMap((p) => [p.lat, p.lng]).map((n) => n.toFixed(5)).join(',');
          try {
            const cached = localStorage.getItem(key);
            if (cached) return JSON.parse(cached);
          } catch (e) { /* storage unavailable */ }

          const url = 'https://router.project-osrm.org/route/v1/driving/'
            + stops.map((p) => p.lng + ',' + p.lat).join(';')
            + '?overview=full&geometries=geojson';
          const res = await fetch(url);
          if (!res.ok) throw new Error('OSRM ' + res.status);
          const data = await res.json();
          if (data.code !== 'Ok' || !data.routes?.length) return null;

          const best = data.routes[0];
          const route = {
            path: best.geometry.coordinates.map(([lng, lat]) => [lat, lng]),
            distance: best.distance,
            duration: best.duration,
          };
          try { localStorage.setItem(key, JSON.stringify(route)); } catch (e) { /* storage full */ }
          return route;
        }

        const lines = routes.map((r) => {
          [r.from, ...r.via, r.to].forEach(addPoint);
          // Placeholder straight line until the road route arrives.
          return L.polyline([r.from, ...r.via, r.to].map((p) => [p.lat, p.lng]), {
            color: '#64748b', weight: 3, dashArray: '8 8', opacity: 0.8,
          }).addTo(map).bindPopup(r.popup);
        });

        // Each truck's latest shared position, where its driver is sharing it.
        live.forEach((p) => {
          bounds.extend([p.lat, p.lng]);
          L.circleMarker([p.lat, p.lng], {
            radius: 10, color: '#fff', weight: 3, fillColor: p.live ? '#16a34a' : '#94a3b8', fillOpacity: 1,
          }).addTo(map).bindTooltip('🚚 ' + (p.plate || p.driver || 'Vehicle') + ' · ' + p.ago, { permanent: true, direction: 'right', offset: [10, 0] });
        });

        fit();
        setTimeout(() => { map.invalidateSize(); fit(); }, 200);

        // Recenter button: zooms back out to the whole route.
        const Recenter = L.Control.extend({
          options: { position: 'topleft' },
          onAdd() {
            const btn = L.DomUtil.create('a', 'leaflet-bar');
            btn.href = '#';
            btn.title = 'Show whole route';
            btn.setAttribute('aria-label', 'Show whole route');
            btn.innerHTML = '&#8982;';
            btn.style.cssText = 'display:block;width:30px;height:30px;line-height:30px;text-align:center;background:#fff;color:#333;font-size:18px;text-decoration:none;';
            L.DomEvent.on(btn, 'click', (e) => { L.DomEvent.preventDefault(e); fit(); });
            return btn;
          },
        });
        map.addControl(new Recenter());

        // One request at a time, to stay within the free service's fair-use limits.
        (async () => {
          for (let i = 0; i < routes.length; i++) {
            const r = routes[i];
            const samePlace = !r.via.length && r.from.lat === r.to.lat && r.from.lng === r.to.lng;
            let route = null;

            if (!samePlace) {
              try { route = await roadRoute(r); } catch (e) { route = null; }
            }

            if (route) {
              const color = colors[r.status] || '#1e3a5f';
              const popup = r.popup + '<br>' + formatTrip(route.distance, route.duration);
              map.removeLayer(lines[i]);
              L.polyline(route.path, { color: '#fff', weight: 8, opacity: 0.9 }).addTo(map);
              L.polyline(route.path, { color, weight: 5 }).addTo(map).bindPopup(popup);
              route.path.forEach((pt) => bounds.extend(pt));
              if (summary) summary.innerHTML = 'Suggested route: <strong>' + formatTrip(route.distance, route.duration) + '</strong>';
            } else if (summary) {
              summary.textContent = samePlace
                ? 'Origin and destination are at the same place.'
                : 'No road route found, so a straight line is shown.';
            }
          }
          fit();
        })();
      });
    </script>
  @endif
</div>
