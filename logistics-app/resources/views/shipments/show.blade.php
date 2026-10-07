@extends('layouts.app')
@section('title', $shipment->tracking_number)
@section('heading', $shipment->tracking_number)

@push('head')
<style>
  .kv { margin:0; font-size:.9rem; line-height:1.6; }
  .kv span { color:var(--muted); }
  .timeline { list-style:none; margin:0; padding:0; }
  .timeline li { padding:10px 0; border-bottom:1px solid var(--border); font-size:.9rem; display:flex; gap:12px; align-items:baseline; flex-wrap:wrap; }
  .timeline time { color:var(--muted); font-size:.8rem; margin-left:auto; }
  a.btn { text-decoration:none; display:inline-block; }
  .delivery-form .field { margin-bottom:14px; }
  .delivery-form label { display:block; font-size:.82rem; font-weight:600; margin-bottom:5px; }
  .delivery-form select, .delivery-form input:not([type=file]) { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.95rem; }
  .delivery-form input[type=file] { font:inherit; font-size:.9rem; }
  .delivery-form .hint { display:block; margin-top:4px; color:var(--muted); font-size:.78rem; }
  .delivery-form .errors { color:#d13438; font-size:.85rem; margin:0 0 14px; padding-left:18px; }
  [data-theme="dark"] .delivery-form .errors { color:#ff6b6f; }
  .delivery-form .btn.primary { width:100%; padding:12px; }
  .share-card .share-status { font-weight:600; margin:0 0 12px; }
  .share-card .share-status.on { color:#16a34a; }
  .share-card .share-status.err { color:#d13438; }
  .share-card .btn { width:100%; padding:12px; }
  .share-card .hint { color:var(--muted); font-size:.78rem; }
  .notes { list-style:none; margin:0 0 16px; padding:0; display:flex; flex-direction:column; gap:14px; }
  .notes li { display:flex; gap:10px; align-items:flex-start; }
  .notes .avatar { width:32px; height:32px; font-size:.75rem; }
  .notes .note-body { flex:1; min-width:0; background:var(--hover); border-radius:4px 12px 12px 12px; padding:8px 12px; }
  .notes li.mine .note-body { background:color-mix(in srgb, var(--primary) 12%, var(--card)); }
  .notes .note-meta { font-size:.78rem; color:var(--muted); margin-bottom:2px; }
  .notes .note-meta strong { color:var(--text); font-size:.85rem; }
  .notes .note-meta { display:flex; flex-wrap:wrap; gap:0 6px; align-items:baseline; }
  .notes .note-time { margin-left:auto; white-space:nowrap; font-variant-numeric:tabular-nums; }
  .notes li.new .note-body { animation:note-in .6s ease-out; }
  @keyframes note-in { from { box-shadow:0 0 0 2px var(--primary); } to { box-shadow:0 0 0 2px transparent; } }
  .notes p { margin:0; font-size:.92rem; white-space:pre-line; overflow-wrap:anywhere; }
  .notes-empty { color:var(--muted); font-size:.9rem; margin:0 0 14px; }
  .note-form { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; }
  .note-form textarea { flex:1; min-width:220px; padding:9px 12px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.92rem; resize:vertical; }
  .note-form textarea:focus { outline:2px solid var(--primary); outline-offset:1px; }
  .note-error { width:100%; margin:0; color:#d13438; font-size:.85rem; }
  .call-link { color:var(--primary); text-decoration:none; font-weight:600; white-space:nowrap; }
  .call-link:hover { text-decoration:underline; }
  /* ---- Layout: summary on top, then main column + sticky actions ---- */
  .sd-summary { padding:20px 24px; margin-bottom:20px; }
  .sd-summary-top { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
  .sd-badges { display:flex; gap:6px; flex-wrap:wrap; }
  .sd-route { display:grid; grid-template-columns:minmax(0,1fr) auto minmax(0,1fr); align-items:center; gap:16px; margin:18px 0; }
  .sd-end { display:flex; flex-direction:column; gap:2px; min-width:0; font-size:.88rem; color:var(--muted); }
  .sd-end:last-child { text-align:right; }
  .sd-end strong { color:var(--text); font-size:1.08rem; }
  .sd-end-label { font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--primary); }
  .sd-arrow { position:relative; width:clamp(80px, 22vw, 260px); height:2px; background:repeating-linear-gradient(to right, var(--border) 0 8px, transparent 8px 14px); }
  .sd-arrow span { position:absolute; left:50%; top:50%; transform:translate(-50%,-50%) scaleX(-1); width:34px; height:34px; display:grid; place-items:center; border-radius:50%; background:var(--card); border:1px solid var(--border); font-size:1rem; }
  .sd-facts { display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:12px; margin:0; padding-top:16px; border-top:1px solid var(--border); }
  .sd-more-stops { font-size:.82rem; color:var(--primary); text-decoration:none; font-weight:600; }
  .sd-stops { margin-bottom:24px; }
  .sd-stops-head { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
  .sd-stops-head h2 { margin:0; }
  .sd-stop-list { list-style:none; margin:0; padding:0; }
  .sd-stop-list li { display:flex; align-items:center; gap:12px; padding:12px 0; }
  .sd-stop-list li + li { border-top:1px solid var(--border); }
  .sd-stop-no { flex-shrink:0; width:30px; height:30px; display:grid; place-items:center; border-radius:50%; border:2px solid var(--border); font-weight:700; font-size:.85rem; color:var(--muted); }
  .sd-stop-list li.next .sd-stop-no { border-color:var(--primary); color:var(--primary); }
  .sd-stop-list li.done .sd-stop-no { border-color:#1a8a4a; background:#1a8a4a; color:#fff; }
  .sd-stop-body { flex:1; min-width:0; }
  .sd-stop-body strong, .sd-stop-body span, .sd-stop-body small { display:block; overflow-wrap:anywhere; }
  .sd-stop-body span { color:var(--muted); font-size:.88rem; }
  .sd-stop-body small { color:var(--muted); font-size:.8rem; margin-top:2px; }
  .sd-stop-items { list-style:none; margin:6px 0 0; padding:0; font-size:.86rem; }
  .sd-stop-items li { padding:1px 0; }
  .sd-stop-items span { color:var(--muted); font-size:.78rem; }
  .sd-stop-actions { display:flex; gap:8px; align-items:center; flex-shrink:0; }
  .sd-stop-actions form { margin:0; }
  .sd-stop-actions a.btn { text-decoration:none; }
  .sd-stops-hint { margin:10px 0 0; font-size:.82rem; color:var(--muted); }
  @media (max-width:640px) { .sd-stop-list li { flex-wrap:wrap; } .sd-stop-actions { width:100%; padding-left:42px; } }
  .sd-facts dt { font-size:.7rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); margin-bottom:3px; }
  .sd-facts dd { margin:0; font-weight:600; font-size:.92rem; }
  .sd-fact-sub { display:block; margin-top:2px; font-weight:400; font-size:.8rem; color:var(--muted); }
  .sd-facts .call-link { font-size:.82rem; margin-left:4px; }
  .sd-instructions { margin:14px 0 0; padding:10px 12px; border-radius:8px; background:var(--hover); font-size:.88rem; }

  .sd-layout { display:grid; grid-template-columns:minmax(0,1fr); gap:20px; align-items:start; }
  .sd-layout.with-actions { grid-template-columns:minmax(0,1fr) 340px; }
  .sd-main { min-width:0; }
  .sd-main > .card { margin-bottom:20px; }
  .sd-actions { position:sticky; top:16px; display:flex; flex-direction:column; gap:16px; }
  .sd-action { padding:18px; margin:0; max-width:none; }
  .sd-action h2 { font-size:1rem; margin:0 0 12px; }
  .sd-stack { display:flex; flex-direction:column; gap:8px; }
  .sd-stack select, .sd-stack input { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.92rem; }
  .sd-stack .btn { width:100%; padding:11px; }
  .sd-action .hint { color:var(--muted); font-size:.78rem; }
  .sd-more { margin-top:10px; font-size:.82rem; color:var(--muted); }
  .sd-more summary { cursor:pointer; color:var(--primary); }
  .sd-more p { margin:8px 0 0; line-height:1.5; }
  .sd-proof { width:100%; border-radius:10px; border:1px solid var(--border); display:block; margin-bottom:10px; }
  .sd-proof-open { display:block; width:100%; padding:0; border:0; background:none; cursor:zoom-in; }
  .sd-proof-open:focus-visible { outline:2px solid var(--primary); outline-offset:2px; border-radius:10px; }
  .sd-proof-dialog { padding:0; border:0; border-radius:12px; background:transparent; max-width:min(96vw, 1100px); max-height:92vh; overflow:visible; }
  .sd-proof-dialog::backdrop { background:rgba(8,12,24,.8); }
  .sd-proof-dialog img { display:block; max-width:min(96vw, 1100px); max-height:92vh; border-radius:12px; }
  .sd-proof-close { position:absolute; top:8px; right:8px; width:40px; height:40px; border:0; border-radius:50%; background:rgba(0,0,0,.6); color:#fff; font-size:1.5rem; line-height:1; cursor:pointer; }

  /* ---- Tabs: notes, items, history, trucks ---- */
  .sd-tabs { padding:0; overflow:hidden; }
  .sd-tablist { display:flex; gap:2px; padding:0 12px; border-bottom:1px solid var(--border); overflow-x:auto; }
  .sd-tab { flex-shrink:0; display:flex; align-items:center; gap:6px; padding:14px 12px 12px; border:0; border-bottom:2px solid transparent; background:none; color:var(--muted); font:inherit; font-weight:600; font-size:.9rem; cursor:pointer; }
  .sd-tab:hover { color:var(--text); }
  .sd-tab[aria-selected="true"] { color:var(--text); border-bottom-color:var(--primary); }
  .sd-tab:focus-visible { outline:2px solid var(--primary); outline-offset:-2px; border-radius:6px; }
  .sd-count { min-width:20px; padding:1px 7px; border-radius:99px; background:var(--hover); color:var(--muted); font-size:.72rem; text-align:center; }
  .sd-tab[aria-selected="true"] .sd-count { background:color-mix(in srgb, var(--primary) 18%, transparent); color:var(--primary); }
  .sd-panel { padding:18px 20px 20px; }
  .sd-panel[hidden] { display:none; }
  .sd-panel-intro { margin:0 0 10px; color:var(--muted); font-size:.88rem; }

  @media (max-width:1100px) {
    .sd-layout.with-actions { grid-template-columns:minmax(0,1fr); }
    .sd-actions { position:static; order:-1; }  /* on phones, actions come first */
  }
  @media (max-width:640px) {
    .sd-summary { padding:16px; }
    .sd-route { grid-template-columns:1fr; gap:10px; }
    .sd-end:last-child { text-align:left; }
    .sd-arrow { width:2px; height:28px; margin-left:16px; background:repeating-linear-gradient(to bottom, var(--border) 0 6px, transparent 6px 10px); }
    .sd-arrow span { display:none; }
    .sd-facts { grid-template-columns:1fr 1fr; gap:10px 12px; } /* keeps the driver's actions close to the top */
  }
  .visually-hidden { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; }
</style>
@endpush

@section('content')
  @php
    $me = auth()->user();
    $canManage = $me->isSuperAdmin() || $me->hasRole('manager', 'logistics_coordinator');
    $isAssigned = $me->hasRole('field_personnel') && $shipment->isAssignedToDriver($me->driver);
    $fieldOptions = $isAssigned ? $shipment->fieldNextStatuses() : [];
    $canShare = $isAssigned && in_array($shipment->status, \App\Http\Controllers\TrackingController::TRACKABLE_STATUSES, true);
    $officeCanUpdate = $canManage && count($shipment->allowedNextStatuses());
    $hasProof = $shipment->proof_photo_path || $shipment->received_by;
    $hasActions = $fieldOptions || $canShare || $officeCanUpdate || $hasProof || $crew;
    $units = $shipment->items->sum('quantity');
    $place = fn ($city, $province, $postal) => collect([$city, $province, $postal])->filter()->join(', ');
  @endphp

  {{-- 1. Summary: status, route and the key facts at a glance. --}}
  <section class="card sd-summary" aria-label="Shipment summary">
    <div class="sd-summary-top">
      <div class="sd-badges">
        <span class="badge {{ $shipment->badgeClass() }}">{{ __($shipment->statusLabel()) }}</span>
        @if ($shipment->delivery_result === 'on_time') <span class="badge b-delivered">{{ __('Delivered on time') }}</span>
        @elseif ($shipment->delivery_result === 'late') <span class="badge b-delayed">{{ __('Delivered late') }}</span> @endif
      </div>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a class="btn sm" href="{{ route('shipments.print', $shipment) }}" target="_blank" rel="noopener">{{ __('Print / PDF') }}</a>
        <a class="btn sm" href="{{ route('shipments.index') }}">{{ __('All shipments') }}</a>
      </div>
    </div>

    <div class="sd-route">
      <div class="sd-end">
        <span class="sd-end-label">{{ __('From') }}</span>
        <strong>{{ $shipment->origin_name }}</strong>
        <span>{{ $shipment->origin_address }}</span>
        <span>{{ $place($shipment->origin_city, $shipment->origin_province, $shipment->origin_postal_code) }}</span>
        @if ($shipment->pickups->count() > 1)
          <a class="sd-more-stops" href="#pickups">{{ trans_choice('+ :count more pickup stop|+ :count more pickup stops', $shipment->pickups->count() - 1) }}</a>
        @endif
      </div>
      <div class="sd-arrow" aria-hidden="true"><span>🚚</span></div>
      <div class="sd-end">
        <span class="sd-end-label">{{ __('To') }}</span>
        <strong>{{ $shipment->destination_name }}</strong>
        <span>{{ $shipment->destination_address }}</span>
        <span>{{ $place($shipment->destination_city, $shipment->destination_province, $shipment->destination_postal_code) }}</span>
      </div>
    </div>

    <dl class="sd-facts">
      <div><dt>{{ __('Scheduled') }}</dt><dd>{{ $shipment->scheduled_delivery_at->translatedFormat('M j, g:i A') }}</dd></div>
      <div><dt>{{ __('Dispatched') }}</dt><dd>{{ $shipment->dispatched_at?->translatedFormat('M j, g:i A') ?? '—' }}</dd></div>
      <div><dt>{{ __('Delivered') }}</dt><dd>{{ $shipment->actual_delivery_at?->translatedFormat('M j, g:i A') ?? '—' }}</dd></div>
      <div>
        <dt>{{ __('Driver') }}</dt>
        <dd>{{ $shipment->driver?->name ?? __('Unassigned') }}
          @if ($canManage && $shipment->driver?->phone)
            <a class="call-link" href="tel:{{ \App\Models\EmergencyContact::dialable($shipment->driver->phone) }}" title="{{ __('Call') }} {{ $shipment->driver->phone }}">📞 {{ __('Call') }}</a>
          @endif
        </dd>
      </div>
      <div>
        <dt>{{ __('Vehicle') }}</dt>
        <dd>{{ $shipment->vehicle?->plate_number ?? __('Unassigned') }}
          @if ($shipment->vehicle?->type) <small class="sd-fact-sub">{{ $shipment->vehicle->type }}</small> @endif
        </dd>
      </div>
      @if ($shipment->helper)
        <div><dt>{{ __('Helper') }}</dt><dd>{{ $shipment->helper->name }}</dd></div>
      @endif
      <div><dt>{{ __('Load') }}</dt><dd>{{ trans_choice(':count line|:count lines', $shipment->items->count()) }} · {{ trans_choice(':count unit|:count units', $units, ['count' => number_format($units)]) }}</dd></div>
    </dl>

    @if ($shipment->notes)
      <p class="sd-instructions"><strong>{{ __('Instructions:') }}</strong> {{ $shipment->notes }}</p>
    @endif
  </section>

  <div class="sd-layout {{ $hasActions ? 'with-actions' : '' }}">
    {{-- 2. Main column: where it is, then everything else in tabs. --}}
    <div class="sd-main">
      {{-- Several places to collect from: each stop is ticked off as it's collected. --}}
      @if ($shipment->pickups->isNotEmpty())
        @php
          $collectedCount = $shipment->pickups->filter->isCollected()->count();
          $stopsOpen = $shipment->status !== 'pending' && in_array($shipment->status, \App\Models\Shipment::OPEN_STATUSES, true);
          $canCollect = ($canManage || $isAssigned) && $stopsOpen;
          $nextStop = $shipment->nextPickup();
        @endphp
        <section class="card sd-stops" id="pickups">
          <div class="sd-stops-head">
            <h2>{{ __('Pickup stops') }}</h2>
            <span class="badge {{ $collectedCount === $shipment->pickups->count() ? 'b-delivered' : 'b-pending' }}">{{ __(':done of :total collected', ['done' => $collectedCount, 'total' => $shipment->pickups->count()]) }}</span>
          </div>
          <ol class="sd-stop-list">
            @foreach ($shipment->pickups as $stop)
              <li class="{{ $stop->isCollected() ? 'done' : ($nextStop && $stop->is($nextStop) ? 'next' : '') }}">
                <span class="sd-stop-no" aria-hidden="true">{{ $stop->sequence }}</span>
                <div class="sd-stop-body">
                  <strong>{{ $stop->name }}</strong>
                  <span>{{ collect([$stop->address, $stop->city, $stop->province])->filter()->implode(', ') }}</span>
                  @php $stopItems = $shipment->items->where('pickup_sequence', $stop->sequence); @endphp
                  @if ($stopItems->isNotEmpty())
                    <ul class="sd-stop-items">
                      @foreach ($stopItems as $item)
                        <li>{{ number_format($item->quantity) }} × {{ $item->item_name }} <span>{{ $item->sku }}</span></li>
                      @endforeach
                    </ul>
                  @endif
                  @if ($stop->isCollected())
                    <small>{{ __('Collected :when', ['when' => $stop->picked_up_at->translatedFormat('M j, g:i A')]) }}@if ($stop->collector) · {{ $stop->collector->name }}@endif</small>
                  @elseif ($stop->scheduled_at)
                    <small>{{ __('Planned for :when', ['when' => $stop->scheduled_at->translatedFormat('M j, g:i A')]) }}</small>
                  @endif
                </div>
                <div class="sd-stop-actions">
                  @if ($stop->isCollected())
                    <span class="badge b-delivered">{{ __('Collected') }}</span>
                  @else
                    <a class="btn sm" href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($stop->mapsQuery()) }}" target="_blank" rel="noopener">{{ __('Directions') }}</a>
                    @if ($canCollect)
                      <form method="POST" action="{{ route('shipments.pickups.collect', [$shipment, $stop]) }}"
                            onsubmit="return confirm(@js(__('Mark this pickup as collected? This cannot be undone.')))">
                        @csrf
                        <button type="submit" class="btn sm primary">{{ __('Mark collected') }}</button>
                      </form>
                    @endif
                  @endif
                </div>
              </li>
            @endforeach
          </ol>
          @if (! $stopsOpen && $shipment->status === 'pending' && ($canManage || $isAssigned))
            <p class="sd-stops-hint">{{ __('Stops can be ticked off once the shipment is released: marked Ready for pickup, or dispatched.') }}</p>
          @endif
        </section>
      @endif

      @include('shipments._route-map', [
        'shipments' => $shipment,
        'mapId' => 'shipment-route-map',
        'live' => $live ?? null,
        'height' => '340px',
        'emptyMessage' => "Couldn't place this route on the map yet. Check that the origin and destination addresses are correct, then reload the page.",
      ])

      <section class="card sd-tabs">
        @php
          $tabs = ['notes' => [__('Notes'), $shipment->shipmentNotes->count()], 'items' => [__('Items'), $shipment->items->count()], 'history' => [__('Tracking history'), $shipment->history->count()]];
          if ($shipment->allocations->isNotEmpty()) { $tabs['trucks'] = [__('Trucks'), $shipment->allocations->count()]; }
        @endphp
        <div class="sd-tablist" role="tablist" aria-label="Shipment details">
          @foreach ($tabs as $key => [$label, $count])
            <button type="button" class="sd-tab" role="tab" id="tab-{{ $key }}" aria-controls="{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}">
              {{ $label }} <span class="sd-count">{{ $count }}</span>
            </button>
          @endforeach
        </div>

        <div class="sd-panel" role="tabpanel" id="notes" aria-labelledby="tab-notes">
          <p class="notes-empty" id="notesEmpty" @unless ($shipment->shipmentNotes->isEmpty()) hidden @endunless>{{ __('No notes yet. Use notes for anything the team should know about this delivery.') }}</p>
          {{-- New notes from anyone appear here live (see the notes script below). --}}
          <ul class="notes" id="notesList" aria-live="polite"
              data-feed="{{ route('shipments.notes.index', $shipment) }}"
              data-last-id="{{ $shipment->shipmentNotes->max('id') ?? 0 }}">
            @foreach ($shipment->shipmentNotes as $n)
              @include('shipments._note', ['n' => $n])
            @endforeach
          </ul>

          @if (\App\Http\Controllers\ShipmentNoteController::canPost($me, $shipment))
            <form class="note-form" id="noteForm" method="POST" action="{{ route('shipments.notes.store', $shipment) }}">
              @csrf
              @error('body', 'note') <p class="note-error">{{ $message }}</p> @enderror
              <label for="note_body" class="visually-hidden">{{ __('Add a note') }}</label>
              <textarea id="note_body" name="body" rows="2" maxlength="1000" required placeholder="{{ __('Add a note, e.g. gate code, receiving hours, a delay…') }}">{{ old('body') }}</textarea>
              <button type="submit" class="btn primary sm">{{ __('Post note') }}</button>
            </form>
          @endif
        </div>

        <div class="sd-panel" role="tabpanel" id="items" aria-labelledby="tab-items" hidden>
          <div class="table-wrap">
            <table>
              <thead><tr><th>SKU</th><th>{{ __('Item') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Weight') }}</th></tr></thead>
              <tbody>
                @foreach ($shipment->items as $item)
                  <tr>
                    <td>{{ $item->sku }}</td><td><strong>{{ $item->item_name }}</strong></td><td>{{ number_format($item->quantity) }}</td>
                    <td>{{ $item->unit_weight_kg !== null ? number_format($item->unit_weight_kg * $item->quantity, 2).' kg' : '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        <div class="sd-panel" role="tabpanel" id="history" aria-labelledby="tab-history" hidden>
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

        @if ($shipment->allocations->isNotEmpty())
          <div class="sd-panel" role="tabpanel" id="trucks" aria-labelledby="tab-trucks" hidden>
            @if ($shipment->allocations->count() > 1)<p class="sd-panel-intro">{{ __('Split across :count trucks.', ['count' => $shipment->allocations->count()]) }}</p>@endif
            <div class="table-wrap">
              <table>
                <thead><tr><th>#</th><th>{{ __('Vehicle') }}</th><th>{{ __('Driver') }}</th><th>{{ __('Load') }}</th><th>{{ __('Capacity') }}</th><th>{{ __('Fill') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                  @foreach ($shipment->allocations as $a)
                    <tr>
                      <td>{{ $a->sequence }}</td>
                      <td><strong>{{ $a->vehicle->plate_number }}</strong> ({{ $a->vehicle->type }})</td>
                      <td>{{ $a->driver?->name ?? 'Unassigned' }}
                        @if ($canManage && $a->driver?->phone)<br><a class="call-link" href="tel:{{ \App\Models\EmergencyContact::dialable($a->driver->phone) }}">📞 {{ $a->driver->phone }}</a>@endif</td>
                      <td>{{ number_format($a->allocated_weight_kg, 2) }} kg</td>
                      <td>{{ number_format($a->vehicle_capacity_kg, 2) }} kg</td>
                      <td>{{ $a->loadPercent() }}%</td>
                      <td><span class="badge {{ \App\Models\Vehicle::badge($a->status === 'dispatched' ? 'on_road' : $a->status) }}">{{ ucfirst($a->status) }}</span></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @endif
      </section>
    </div>

    {{-- 3. Actions: what you can do now, kept in view while scrolling (first on phones). --}}
    @if ($hasActions)
      {{-- Not <aside>: the layout styles every aside as the navigation sidebar. --}}
      <div class="sd-actions" role="complementary" aria-label="Actions">
        @if ($officeCanUpdate)
          <section class="card sd-action">
            <h2>{{ __('Update status') }}</h2>
            <form class="sd-stack" method="POST" action="{{ route('shipments.status', $shipment) }}">
              @csrf @method('PATCH')
              <select name="status" aria-label="New status" data-status-help="office_status_help">
                @foreach ($shipment->allowedNextStatuses() as $next)
                  <option value="{{ $next }}" data-desc="{{ __(\App\Models\Shipment::description($next)) }}">{{ __('Mark as :status', ['status' => __(\App\Models\Shipment::label($next))]) }}</option>
                @endforeach
              </select>
              <small class="hint" id="office_status_help" aria-live="polite"></small>
              <input type="text" name="note" placeholder="{{ __('Note (optional)') }}" maxlength="500" aria-label="{{ __('Note') }}">
              <button type="submit" class="btn primary">{{ __('Update status') }}</button>
            </form>
          </section>
        @endif

        {{-- Super Admin: change who takes this shipment while it isn't finished. --}}
        @if ($crew)
          <section class="card sd-action" id="edit-assignment">
            <h2>{{ __('Edit assignment') }}</h2>
            @if ($errors->hasAny(['driver_id', 'vehicle_id', 'helper_id']))
              <ul class="errors">@foreach (['driver_id', 'vehicle_id', 'helper_id'] as $f)@foreach ($errors->get($f) as $e)<li>{{ $e }}</li>@endforeach @endforeach</ul>
            @endif
            <form class="sd-stack" method="POST" action="{{ route('shipments.crew', $shipment) }}">
              @csrf @method('PATCH')
              <label for="crew_driver_id">{{ __('Driver') }}</label>
              <select id="crew_driver_id" name="driver_id" required><option value="">{{ __('Select…') }}</option>
                @foreach ($crew['drivers'] as $d)
                  @php $on = $crew['busy']['drivers'][$d->id] ?? null; @endphp
                  <option value="{{ $d->id }}" @selected(old('driver_id', $shipment->driver_id) == $d->id) @disabled($on)>{{ $d->name }}@if ($on) (on {{ $on->tracking_number }})@endif</option>
                @endforeach
              </select>
              <label for="crew_vehicle_id">{{ __('Vehicle') }}</label>
              <select id="crew_vehicle_id" name="vehicle_id" required><option value="">{{ __('Select…') }}</option>
                @foreach ($crew['vehicles'] as $v)
                  @php $on = $crew['busy']['vehicles'][$v->id] ?? null; @endphp
                  <option value="{{ $v->id }}" @selected(old('vehicle_id', $shipment->vehicle_id) == $v->id) @disabled($on)>{{ $v->plate_number }}{{ $v->type ? " - {$v->type}" : '' }}@if ($on) (on {{ $on->tracking_number }})@endif</option>
                @endforeach
              </select>
              @if ($crew['helpers']->count())
                <label for="crew_helper_id">{{ __('Helper') }}</label>
                <select id="crew_helper_id" name="helper_id"><option value="">{{ __('Unassigned') }}</option>
                  @foreach ($crew['helpers'] as $h)
                    @php $on = $crew['busy']['helpers'][$h->id] ?? null; @endphp
                    <option value="{{ $h->id }}" @selected(old('helper_id', $shipment->helper_id) == $h->id) @disabled($on)>{{ $h->name }}@if ($on) (on {{ $on->tracking_number }})@endif</option>
                  @endforeach
                </select>
              @endif
              <button type="submit" class="btn">{{ __('Save assignment') }}</button>
              <small class="hint">{{ __('Greyed-out choices are still out on another shipment.') }}</small>
            </form>
          </section>
        @endif

        @if ($fieldOptions)
          <form class="card sd-action delivery-form" id="delivery-update" method="POST" action="{{ route('shipments.field-update', $shipment) }}" enctype="multipart/form-data">
            @csrf
            <h2>{{ __('Update delivery') }}</h2>
            @if ($errors->any())
              <ul class="errors">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            @endif
            <div class="field">
              <label for="field_status">{{ __('What happened?') }}</label>
              {{-- No default: finishing a delivery by accident releases its stock and ends tracking. --}}
              <select id="field_status" name="status" required data-status-help="field_status_help">
                <option value="" disabled @selected(! old('status'))>{{ __('Choose what happened…') }}</option>
                @foreach ($fieldOptions as $next)
                  <option value="{{ $next }}" data-desc="{{ __(\App\Models\Shipment::description($next)) }}" @selected(old('status') === $next)>{{ $next === 'in_transit' && $shipment->status === 'delayed' ? __('Back on the road (in transit)') : __(\App\Models\Shipment::label($next)) }}</option>
                @endforeach
              </select>
              <small class="hint" id="field_status_help" aria-live="polite"></small>
            </div>
            <div data-when="delivered">
              <div class="field">
                <label for="received_by">{{ __('Received by') }}</label>
                <input id="received_by" name="received_by" value="{{ old('received_by') }}" maxlength="150" placeholder="{{ __('Name of the person who received it') }}" autocomplete="off">
              </div>
              <div class="field">
                <label for="proof_photo">{{ __('Proof of delivery photo') }}</label>
                <input id="proof_photo" type="file" name="proof_photo" accept="image/jpeg,image/png,image/webp" capture="environment">
                <small class="hint" id="proof_photo_hint" aria-live="polite">{{ __('Opens the camera on a phone. Large photos are made smaller automatically before sending.') }}</small>
              </div>
            </div>
            <div class="field">
              <label for="field_note" id="field_note_label">{{ __('Note (optional)') }}</label>
              <input id="field_note" name="note" value="{{ old('note') }}" maxlength="500" placeholder="{{ __('e.g. left with the guard, road closed…') }}" autocomplete="off">
            </div>
            <button type="submit" class="btn primary">{{ __('Save update') }}</button>
          </form>
        @endif

        @if ($canShare)
          <section class="card sd-action share-card" id="share-location">
            <h2>{{ __('Share my location') }}</h2>
            <p class="share-status" id="share-status" aria-live="polite">{{ __('Not sharing.') }}</p>
            <button type="button" class="btn primary" id="share-toggle">{{ __('Start sharing') }}</button>
            <details class="sd-more">
              <summary>{{ __('How it works') }}</summary>
              <p>{{ __('While this page is open, your phone sends its location about once a minute so the office can see where the delivery is. It stops when you press Stop, close this page, or the delivery is finished. Keep the screen on: if the phone locks, updates pause until you come back. Location is kept for :days days.', ['days' => \App\Models\VehicleLocationPing::KEEP_DAYS]) }}</p>
            </details>
          </section>
        @endif

        @if ($hasProof)
          <section class="card sd-action">
            <h2>{{ __('Proof of delivery') }}</h2>
            @if ($shipment->proofPhotoUrl())
              <button type="button" class="sd-proof-open" id="proofOpen" aria-haspopup="dialog" aria-label="{{ __('View photo larger') }}">
                <img class="sd-proof" src="{{ $shipment->proofPhotoUrl() }}" alt="{{ __('Proof of delivery photo') }}">
              </button>
              <dialog class="sd-proof-dialog" id="proofDialog" aria-label="{{ __('Proof of delivery photo') }}">
                <button type="button" class="sd-proof-close" id="proofClose" aria-label="{{ __('Close') }}">&times;</button>
                <img src="{{ $shipment->proofPhotoUrl() }}" alt="{{ __('Proof of delivery photo') }}">
              </dialog>
            @endif
            <p class="kv">
              <span>{{ __('Received by:') }}</span> {{ $shipment->received_by ?? '—' }}<br>
              <span>{{ __('Delivered:') }}</span> {{ $shipment->actual_delivery_at?->translatedFormat('M j, Y g:i A') ?? '—' }}
            </p>
          </section>
        @endif
      </div>
    @endif
  </div>
@endsection

@push('scripts')
<script>
  // Delivery form: photo + receiver only for "Delivered"; a reason is required for "Delayed".
  // The proof photo opens larger in a popup over the page. Esc, the close button or a
  // click outside the photo closes it.
  (function () {
    const dialog = document.getElementById('proofDialog');
    if (!dialog || !dialog.showModal) return;
    document.getElementById('proofOpen').addEventListener('click', () => dialog.showModal());
    document.getElementById('proofClose').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
  })();

  // Shrink the proof photo in the browser before it is sent: phone cameras produce files
  // far bigger than the server accepts, and a smaller one uploads faster on mobile data.
  // If anything here fails, the original file is left in place and sent as it is.
  (function () {
    const input = document.getElementById('proof_photo');
    if (!input || !window.DataTransfer || !HTMLCanvasElement.prototype.toBlob) return;
    const hint = document.getElementById('proof_photo_hint');
    const MAX_SIDE = 1600, QUALITY = 0.8, SKIP_UNDER = 600 * 1024;
    const T = {{ Js::from(['working' => __('Preparing photo…'), 'ready' => __('Photo ready to send')]) }};
    const kb = (bytes) => bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';

    input.addEventListener('change', async () => {
      const file = input.files[0];
      if (!file || !file.type.startsWith('image/')) return;
      if (file.size <= SKIP_UNDER) { hint.textContent = T.ready + ' (' + kb(file.size) + ')'; return; }
      hint.textContent = T.working;
      const url = URL.createObjectURL(file);
      try {
        const img = new Image();
        await new Promise((ok, fail) => { img.onload = ok; img.onerror = fail; img.src = url; });
        const scale = Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((ok) => canvas.toBlob(ok, 'image/jpeg', QUALITY));
        if (blob && blob.size < file.size && input.files[0] === file) {
          const swap = new DataTransfer();
          swap.items.add(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
          input.files = swap.files;
        }
      } catch (e) { /* keep the original */ }
      URL.revokeObjectURL(url);
      hint.textContent = T.ready + ' (' + kb(input.files[0].size) + ')';
    });
  })();

  // Under each status dropdown, explain what the chosen status means.
  document.querySelectorAll('select[data-status-help]').forEach((select) => {
    const help = document.getElementById(select.dataset.statusHelp);
    const sync = () => { help.textContent = select.selectedOptions[0]?.dataset.desc || ''; };
    select.addEventListener('change', sync);
    sync();
  });

  (function () {
    const status = document.getElementById('field_status');
    if (!status) return;
    const delivered = document.querySelector('[data-when="delivered"]');
    const note = document.getElementById('field_note');
    const noteLabel = document.getElementById('field_note_label');
    const problems = @json(\App\Models\Shipment::PROBLEM_STATUSES);
    function sync() {
      const s = status.value;
      delivered.hidden = s !== 'delivered';
      document.getElementById('received_by').required = s === 'delivered';
      document.getElementById('proof_photo').required = s === 'delivered';
      note.required = problems.includes(s);
      noteLabel.textContent = s === 'delayed' ? @json(__('Reason for the delay')) : (s === 'delivery_attempted' ? @json(__('Why could it not be delivered?')) : @json(__('Note (optional)')));
    }
    status.addEventListener('change', sync);
    sync();
  })();
</script>
@endpush

@push('scripts')
<script>
  // Location sharing: only after the driver presses Start. The browser also asks for
  // permission the first time. Positions go out at most once a minute while the page is open.
  (function () {
    const btn = document.getElementById('share-toggle');
    const S = {{ Js::from(['noBrowser' => __('This browser cannot share location.'), 'needsHttps' => __('Location sharing only works on a secure (https) address. Ask the office for the https link.'), 'stopped' => __('Sharing stopped: this delivery is finished or no longer on the road.'), 'lastSent' => __('Sharing · last sent'), 'retry' => __('Sharing, but the last update did not go through. Will retry.'), 'getting' => __('Getting your location…'), 'refused' => __('Location permission was refused. Allow it in the browser settings to share.'), 'noGps' => __('Could not get your location. Check that GPS is on.'), 'stop' => __('Stop sharing'), 'start' => __('Start sharing'), 'notSharing' => __('Not sharing.')]) }};
    const memoryKey = 'sharing:' + @json($shipment->shipment_id);
    // No sharing card means the delivery is no longer on the road: forget the
    // "keep sharing after reload" flag so it can't carry over.
    if (!btn) { try { sessionStorage.removeItem(memoryKey); } catch (e) {} return; }
    // Browsers refuse to give a position on a plain http address (other than localhost).
    if (!window.isSecureContext) {
      btn.disabled = true; document.getElementById('share-status').textContent = S.needsHttps;
      return;
    }
    if (!('geolocation' in navigator)) {
      if (btn) { btn.disabled = true; document.getElementById('share-status').textContent = S.noBrowser; }
      return;
    }
    const status = document.getElementById('share-status');
    const token = @json(csrf_token());
    const urls = { sharing: @json(route('tracking.sharing', $shipment)), ping: @json(route('tracking.ping', $shipment)) };
    const EVERY_MS = 60000;
    let watchId = null, lastSent = 0, latest = null, timer = null, wakeLock = null;

    const post = (url, body) => fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify(body),
    });

    function show(text, cls) { status.textContent = text; status.className = 'share-status ' + (cls || ''); }

    async function send() {
      if (!latest || Date.now() - lastSent < EVERY_MS - 1000) return;
      lastSent = Date.now();
      try {
        const res = await post(urls.ping, { latitude: latest.latitude, longitude: latest.longitude, accuracy: latest.accuracy });
        if (res.status === 409 || res.status === 403) { stop(false); show(S.stopped); return; }
        if (!res.ok) throw new Error(res.status);
        show(S.lastSent + ' ' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }), 'on');
      } catch (e) {
        show(S.retry, 'err');
        lastSent = 0; // try again on the next position
      }
    }

    async function keepScreenOn() {
      try { if ('wakeLock' in navigator) wakeLock = await navigator.wakeLock.request('screen'); } catch (e) { /* not allowed; fine */ }
    }

    function start(logIt = true) {
      show(S.getting);
      watchId = navigator.geolocation.watchPosition(
        (pos) => { latest = pos.coords; send(); },
        (err) => {
          show(err.code === err.PERMISSION_DENIED
            ? S.refused
            : S.noGps, 'err');
          if (err.code === err.PERMISSION_DENIED) stop(false);
        },
        { enableHighAccuracy: true, maximumAge: 30000, timeout: 30000 }
      );
      timer = setInterval(send, EVERY_MS); // resend even when standing still
      btn.textContent = S.stop;
      btn.classList.remove('primary');
      try { sessionStorage.setItem(memoryKey, '1'); } catch (e) {}
      keepScreenOn();
      if (logIt) post(urls.sharing, { action: 'start' }).catch(() => {});
    }

    function stop(logIt = true) {
      if (watchId !== null) navigator.geolocation.clearWatch(watchId);
      clearInterval(timer);
      watchId = null; latest = null; lastSent = 0;
      btn.textContent = S.start;
      btn.classList.add('primary');
      try { sessionStorage.removeItem(memoryKey); } catch (e) {}
      if (wakeLock) { wakeLock.release().catch(() => {}); wakeLock = null; }
      show(S.notSharing);
      if (logIt) post(urls.sharing, { action: 'stop' }).catch(() => {});
    }

    btn.addEventListener('click', () => (watchId === null ? start() : stop()));

    // The screen lock is dropped when the page is hidden; take it again on return.
    document.addEventListener('visibilitychange', () => { if (watchId !== null && document.visibilityState === 'visible') keepScreenOn(); });

    // Carry on after a reload (e.g. after saving a delivery update), without logging a new start.
    try { if (sessionStorage.getItem(memoryKey) === '1') start(false); } catch (e) {}
  })();
</script>
@endpush

@push('scripts')
<script>
  // Detail tabs (Notes, Items, Tracking history, Trucks). A link to #notes, #items, #history
  // or #trucks, e.g. from an alert, opens that tab.
  (function () {
    const tabs = [...document.querySelectorAll('.sd-tab')];
    if (!tabs.length) return;

    function select(tab, focus) {
      tabs.forEach((t) => {
        const on = t === tab;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
      });
      if (focus) tab.focus();
    }

    tabs.forEach((tab, i) => {
      tab.addEventListener('click', () => {
        select(tab);
        history.replaceState(null, '', '#' + tab.getAttribute('aria-controls'));
      });
      tab.addEventListener('keydown', (e) => {
        const next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
        if (next === undefined) return;
        e.preventDefault();
        select(tabs[(next + tabs.length) % tabs.length], true);
      });
    });

    const fromHash = tabs.find((t) => '#' + t.getAttribute('aria-controls') === location.hash);
    if (fromHash) {
      select(fromHash);
      document.querySelector('.sd-tabs').scrollIntoView({ block: 'start' });
    }
  })();
</script>
@endpush

@push('scripts')
<script>
  // Notes thread: posting without a reload, other people's new notes appearing on their own,
  // and relative times ("just now", "3 minutes ago") that stay current. The exact time is
  // printed beside each note by the server.
  (function () {
    const list = document.getElementById('notesList');
    if (!list) return;
    const empty = document.getElementById('notesEmpty');
    const form = document.getElementById('noteForm');
    const counter = document.querySelector('#tab-notes .sd-count');
    const token = @json(csrf_token());
    const rtf = new Intl.RelativeTimeFormat(@json(app()->getLocale() === 'tl' ? 'fil' : 'en'), { numeric: 'auto' });
    const T = {{ Js::from(['justNow' => __('just now'), 'failed' => __("Couldn't post the note. Please try again.")]) }};

    function relative(date) {
      const secs = Math.round((date - Date.now()) / 1000);
      if (Math.abs(secs) < 45) return T.justNow;
      const steps = [['minute', 60], ['hour', 3600], ['day', 86400], ['week', 604800], ['month', 2592000], ['year', 31536000]];
      let unit = 'minute', size = 60;
      for (const [u, s] of steps) { if (Math.abs(secs) >= s) { unit = u; size = s; } }
      return rtf.format(Math.round(secs / size), unit);
    }
    function refreshTimes() {
      list.querySelectorAll('time[data-relative]').forEach((t) => { t.textContent = relative(new Date(t.getAttribute('datetime'))); });
    }

    // Add rendered notes, skipping any already shown (e.g. your own, just posted).
    function append(html) {
      const tmp = document.createElement('ul');
      tmp.innerHTML = html;
      let added = 0;
      [...tmp.children].forEach((li) => {
        if (list.querySelector(`[data-note-id="${li.dataset.noteId}"]`)) return;
        li.classList.add('new');
        list.appendChild(li);
        list.dataset.lastId = Math.max(Number(list.dataset.lastId), Number(li.dataset.noteId));
        added++;
      });
      if (added) {
        empty.hidden = true;
        if (counter) counter.textContent = list.children.length;
        refreshTimes();
      }
      return added;
    }

    async function poll() {
      try {
        const res = await fetch(list.dataset.feed + '?after=' + list.dataset.lastId, { headers: { Accept: 'application/json' } });
        if (res.ok) append((await res.json()).html);
      } catch (e) { /* offline: try again next time */ }
    }

    if (form) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const button = form.querySelector('button[type=submit]');
        const box = form.querySelector('textarea');
        let error = form.querySelector('.note-error');
        button.disabled = true;
        try {
          const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token } });
          const data = await res.json();
          if (!res.ok) throw new Error(data.errors?.body?.[0] || T.failed);
          append(data.html);
          box.value = '';
          error?.remove();
          list.lastElementChild?.scrollIntoView({ block: 'nearest' });
        } catch (err) {
          if (!error) { error = document.createElement('p'); error.className = 'note-error'; form.prepend(error); }
          error.textContent = err.message || T.failed;
        } finally {
          button.disabled = false;
          box.focus();
        }
      });
    }

    refreshTimes();
    setInterval(() => { if (document.visibilityState === 'visible') poll(); }, 8000);
    setInterval(refreshTimes, 30000);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') { poll(); refreshTimes(); } });
  })();
</script>
@endpush
