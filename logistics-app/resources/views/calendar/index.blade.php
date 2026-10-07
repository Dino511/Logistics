@extends('layouts.app')
@section('title', __('Calendar'))
@section('heading', __('Calendar'))

@php
  // Links keep the current view, month and filters, changing only what's passed in.
  $params = ['view' => $view === 'week' ? 'week' : null, 'date' => $anchor->toDateString(), 'status' => $status, 'driver_id' => $driverId, 'vehicle_id' => $vehicleId];
  $url = fn (array $over = []) => route('calendar.index', array_filter(array_merge($params, $over)));
  $maxChips = $view === 'week' ? 50 : 3;
  $dayEvents = $events[$selected->toDateString()] ?? [];
@endphp

@push('head')
<style>
  .cal-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; margin-bottom:16px; }
  .cal-bar h2 { margin:0; }
  .cal-bar .group, .cal-bar form { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:0; }
  a.btn { text-decoration:none; display:inline-block; }
  a.btn.on { background:var(--primary); border-color:var(--primary); color:#fff; font-weight:600; }
  .cal-grid { display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); border:1px solid var(--border); border-radius:10px; overflow:hidden; }
  .cal-dow { padding:8px; font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); background:var(--hover); text-align:center; }
  .cal-day { display:block; min-height:104px; padding:6px; border-top:1px solid var(--border); border-left:1px solid var(--border); color:var(--text); text-decoration:none; min-width:0; }
  .cal-day:nth-child(7n+1) { border-left:0; }
  .cal-grid.week .cal-day { min-height:220px; }
  .cal-day:hover { background:var(--hover); }
  .cal-day.out .cal-num { color:var(--muted); opacity:.6; }
  .cal-day.sel { box-shadow:inset 0 0 0 2px var(--primary); }
  .cal-num { display:inline-grid; place-items:center; min-width:24px; height:24px; padding:0 4px; border-radius:99px; font-size:.82rem; font-weight:600; }
  .cal-day.today .cal-num { background:var(--primary); color:#fff; }
  .cal-ev { display:block; margin-top:4px; padding:2px 6px; border-radius:6px; border-left:3px solid; font-size:.74rem; line-height:1.35; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .cal-ev strong { display:block; overflow:hidden; text-overflow:ellipsis; }
  .cal-ev.pickup { background:#fff1d6; border-color:#d98a00; color:#7a4d00; }
  .cal-ev.delivery { background:#e3ebff; border-color:#2454e6; color:#1b3fae; }
  [data-theme="dark"] .cal-ev.pickup { background:#3d2f10; color:#ffcf7a; }
  [data-theme="dark"] .cal-ev.delivery { background:#1d2c5c; color:#a9c1ff; }
  .cal-ev.done { opacity:.6; }
  .cal-ev.cancelled { opacity:.5; text-decoration:line-through; }
  .cal-ev.overdue { border-color:#d13438; box-shadow:inset 0 0 0 1px #d13438; }
  .cal-more { display:block; margin-top:4px; font-size:.72rem; color:var(--muted); }
  .cal-legend { display:flex; flex-wrap:wrap; gap:14px; margin-top:12px; font-size:.8rem; color:var(--muted); }
  .cal-legend .cal-ev { display:inline-block; margin:0 6px 0 0; }
  .empty { text-align:center; color:var(--muted); padding:24px 8px; }
  td a.track { color:var(--primary); text-decoration:none; font-weight:600; }
  tr.row-link { cursor:pointer; }
  tr.row-link:hover td { background:var(--hover); }
  /* Phones: seven columns are too narrow for text, so each entry becomes a coloured bar. */
  @media (max-width:760px) {
    .cal-day { min-height:64px; padding:4px; }
    .cal-grid.week .cal-day { min-height:90px; }
    .cal-ev { height:6px; padding:0; font-size:0; border-left-width:0; }
    .cal-ev.pickup { background:#d98a00; } .cal-ev.delivery { background:#2454e6; }
    .cal-legend .cal-ev { width:18px; }
    .cal-more { font-size:.65rem; }
  }
</style>
@endpush

@section('content')
  @if ($isField && ! $isLinkedDriver)
    <div class="card" style="margin-bottom:24px">
      <p class="empty">{{ __("Your account isn't linked to a driver yet. Ask a Manager to link it from the driver's page.") }}</p>
    </div>
  @endif

  <div class="card" style="margin-bottom:24px">
    <div class="cal-bar">
      <div class="group">
        <a class="btn sm" href="{{ $url(['date' => $prevDate, 'day' => null]) }}">{{ __('Previous') }}</a>
        <a class="btn sm" href="{{ $url(['date' => today()->toDateString(), 'day' => null]) }}">{{ __('Today') }}</a>
        <a class="btn sm" href="{{ $url(['date' => $nextDate, 'day' => null]) }}">{{ __('Next') }}</a>
      </div>
      <h2>{{ $title }}</h2>
      <div class="group">
        <a class="btn sm {{ $view === 'month' ? 'on' : '' }}" href="{{ $url(['view' => null, 'date' => $selected->toDateString()]) }}">{{ __('Month') }}</a>
        <a class="btn sm {{ $view === 'week' ? 'on' : '' }}" href="{{ $url(['view' => 'week', 'date' => $selected->toDateString()]) }}">{{ __('Week') }}</a>
      </div>
    </div>

    @unless ($isField)
      <div class="cal-bar">
        <form method="GET" action="{{ route('calendar.index') }}">
          @if ($view === 'week') <input type="hidden" name="view" value="week"> @endif
          <input type="hidden" name="date" value="{{ $anchor->toDateString() }}">
          <select name="status" aria-label="Filter by status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach (\App\Models\Shipment::STATUSES as $s)
              <option value="{{ $s }}" @selected($status === $s)>{{ \App\Models\Shipment::label($s) }}</option>
            @endforeach
          </select>
          <select name="driver_id" aria-label="Filter by driver" onchange="this.form.submit()">
            <option value="">All drivers</option>
            @foreach ($drivers as $d)<option value="{{ $d->id }}" @selected($driverId === $d->id)>{{ $d->name }}</option>@endforeach
          </select>
          <select name="vehicle_id" aria-label="Filter by vehicle" onchange="this.form.submit()">
            <option value="">All vehicles</option>
            @foreach ($vehicles as $v)<option value="{{ $v->id }}" @selected($vehicleId === $v->id)>{{ $v->plate_number }}</option>@endforeach
          </select>
          @if ($status || $driverId || $vehicleId)
            <a class="btn sm" href="{{ $url(['status' => null, 'driver_id' => null, 'vehicle_id' => null]) }}">Clear filters</a>
          @endif
        </form>
      </div>
    @endunless

    <div class="cal-grid {{ $view }}">
      @foreach (array_slice($days, 0, 7) as $d)
        <div class="cal-dow">{{ $d->translatedFormat('D') }}</div>
      @endforeach
      @foreach ($days as $d)
        @php
          $key = $d->toDateString();
          $list = $events[$key] ?? [];
          $classes = array_filter([
            'cal-day',
            $view === 'month' && $d->month !== $anchor->month ? 'out' : null,
            $d->isToday() ? 'today' : null,
            $d->isSameDay($selected) ? 'sel' : null,
          ]);
        @endphp
        <a class="{{ implode(' ', $classes) }}" href="{{ $url(['day' => $key]) }}#day"
           aria-label="{{ $d->translatedFormat('l, F j') }}: {{ count($list) }} {{ __('scheduled') }}" @if ($d->isSameDay($selected)) aria-current="date" @endif>
          <span class="cal-num">{{ $d->day }}</span>
          @foreach (array_slice($list, 0, $maxChips) as $e)
            <span title="{{ $e['shipment']->tracking_number }} · {{ __($e['label']) }}: {{ collect([$e['place'], $e['address'], $e['city']])->filter()->implode(', ') }}" class="cal-ev {{ $e['type'] }} {{ $e['overdue'] ? 'overdue' : '' }} {{ $e['shipment']->status === 'delivered' ? 'done' : '' }} {{ in_array($e['shipment']->status, ['cancelled', 'returned'], true) ? 'cancelled' : '' }}"><strong>{{ $e['shipment']->tracking_number }}</strong>{{ $e['at']->format('g:i A') }} · {{ collect([$e['place'], $e['city']])->filter()->implode(', ') }}</span>
          @endforeach
          @if (count($list) > $maxChips)
            <span class="cal-more">+{{ count($list) - $maxChips }} {{ __('more') }}</span>
          @endif
        </a>
      @endforeach
    </div>

    <div class="cal-legend">
      <span><span class="cal-ev pickup">{{ __('Pickup') }}</span>{{ __('leaving the origin') }}</span>
      <span><span class="cal-ev delivery">{{ __('Delivery') }}</span>{{ __('expected at the destination (ETA)') }}</span>
      <span><span class="cal-ev delivery overdue">{{ __('Overdue') }}</span>{{ __('ETA passed, not delivered yet') }}</span>
    </div>
  </div>

  <div class="card" id="day">
    <h2>{{ $selected->translatedFormat('l, F j, Y') }}</h2>
    @if (empty($dayEvents))
      <p class="empty">{{ __('No pickups or deliveries on this day.') }}</p>
    @else
      <div class="table-wrap">
        <table>
          <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Type') }}</th><th>{{ __('Tracking no.') }}</th><th>{{ __('Where') }}</th><th>{{ __('Status') }}</th><th>{{ __('Driver') }}</th><th>{{ __('Vehicle') }}</th></tr></thead>
          <tbody>
            @foreach ($dayEvents as $e)
              @php
                $sh = $e['shipment'];
                $driverNames = collect([$sh->driver?->name])->merge($sh->allocations->map(fn ($a) => $a->driver?->name))->filter()->unique()->implode(', ');
                $plates = collect([$sh->vehicle?->plate_number])->merge($sh->allocations->map(fn ($a) => $a->vehicle?->plate_number))->filter()->unique()->implode(', ');
              @endphp
              <tr class="row-link" data-href="{{ route('shipments.show', $sh) }}">
                <td>{{ $e['at']->format('g:i A') }}</td>
                <td><span class="cal-ev {{ $e['type'] }}" style="display:inline-block; margin:0; height:auto; padding:2px 6px; font-size:.74rem; border-left-width:3px;">{{ __($e['label']) }}</span></td>
                <td><a class="track" href="{{ route('shipments.show', $sh) }}">{{ $sh->tracking_number }}</a></td>
                <td><strong>{{ $e['place'] }}</strong><br><small style="color:var(--muted)">{{ collect([$e['address'], $e['city']])->filter()->implode(', ') }}</small></td>
                <td>
                  <span class="badge {{ $sh->badgeClass() }}">{{ __($sh->statusLabel()) }}</span>
                  @if ($e['overdue']) <span class="badge b-delayed">{{ __('Overdue') }}</span> @endif
                </td>
                <td>{{ $driverNames ?: '—' }}</td>
                <td>{{ $plates ?: '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
@endsection

@push('scripts')
<script>
  // Clicking anywhere on a row of the day list opens that shipment. The tracking link keeps
  // its own behaviour, and dragging to select text does not navigate.
  document.querySelectorAll('tr.row-link').forEach((row) => {
    row.addEventListener('click', (e) => {
      if (e.target.closest('a, button, input, select, label') || window.getSelection().toString()) return;
      if (e.ctrlKey || e.metaKey) window.open(row.dataset.href, '_blank', 'noopener');
      else window.location.href = row.dataset.href;
    });
  });
</script>
@endpush
