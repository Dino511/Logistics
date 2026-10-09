@extends('layouts.app')
@section('title', __('My dashboard'))
@section('heading', __('My dashboard'))

@php
  use App\Models\Shipment;

  // The usual journey, shown as progress dots on the next delivery. Statuses off the
  // main path sit on the step they interrupt (a delay happens while in transit, etc.).
  $journey = ['ready_for_pickup', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered'];
  $stepOf = ['pending' => -1, 'ready_for_pickup' => 0, 'picked_up' => 1, 'in_transit' => 2, 'delayed' => 2, 'out_for_delivery' => 3, 'delivery_attempted' => 3, 'held_for_pickup' => 3, 'delivered' => 4];

  $hasProblem = $next && in_array($next->status, Shipment::PROBLEM_STATUSES, true);
  $isOverdue = $next && $next->scheduled_delivery_at->isPast();
  $canShare = $next && in_array($next->status, \App\Http\Controllers\TrackingController::TRACKABLE_STATUSES, true);
  $canUpdate = $next && count($next->fieldNextStatuses());
  // With several pickups, the driver heads for the next stop still to be collected.
  $nextStop = $next?->nextPickup();
  // Turn-by-turn directions in the phone's maps app: the pinned spot if there is one, else the address.
  $mapsTo = $nextStop ? $nextStop->mapsQuery() : ($next
    ? ($next->destination_latitude && $next->destination_longitude
        ? $next->destination_latitude.','.$next->destination_longitude
        : collect([$next->destination_address, $next->destination_city, $next->destination_province])->filter()->implode(', '))
    : null);
@endphp

@push('head')
<style>
  .fd { display:grid; gap:16px; grid-template-columns:minmax(0, 1fr); grid-template-areas:"hello" "alerts" "main" "side"; }
  .fd .fd-hello { grid-area:hello; } .fd-alerts { grid-area:alerts; } .fd-main { grid-area:main; } .fd-side { grid-area:side; }
  .fd-main, .fd-side { display:grid; gap:16px; align-content:start; min-width:0; }
  @media (min-width:1100px) {
    .fd { grid-template-columns:minmax(0, 1.7fr) minmax(300px, 1fr); grid-template-areas:"hello hello" "alerts alerts" "main side"; }
  }

  .fd-label { margin:0 0 12px; font-size:.7rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); }
  .fd-empty { color:var(--muted); font-size:.92rem; margin:0; line-height:1.5; }
  .fd a.btn { text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:8px; }
  .fd svg { flex-shrink:0; }

  /* Header: greeting on the left, this week's numbers on the right. */
  .fd .fd-hello { display:flex; flex-wrap:wrap; gap:18px 32px; align-items:center; justify-content:space-between; padding:24px 28px; border:0; border-radius:16px; color:#fff;
    background:linear-gradient(120deg, #12224d 0%, #1c3a8f 55%, #2454e6 100%); }
  .fd-hello h2 { margin:0 0 6px; font-size:1.5rem; color:#fff; }
  .fd-hello p { margin:0; color:rgba(255,255,255,.82); font-size:.95rem; }
  .fd-due-pill { display:inline-block; margin-left:6px; padding:2px 10px; border-radius:99px; background:rgba(255,255,255,.16); color:#fff; font-size:.82rem; font-weight:600; }
  .fd-week { display:grid; grid-template-columns:repeat(3, minmax(88px, 1fr)); gap:10px; margin:0; }
  .fd-week div { padding:10px 14px; border-radius:12px; background:rgba(255,255,255,.12); text-align:center; }
  .fd-week dd { margin:0; font-size:1.45rem; font-weight:700; line-height:1.15; }
  .fd-week dt { font-size:.74rem; color:rgba(255,255,255,.8); }
  .fd-week .warn dd { color:#ffb4b6; }

  .fd .fd-alerts { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 18px; background:color-mix(in srgb, var(--primary) 10%, var(--card)); border-color:color-mix(in srgb, var(--primary) 35%, var(--border)); }

  /* Next delivery: the one thing the driver came here for. */
  .fd .fd-next { padding:24px; border-top:4px solid var(--primary); }
  .fd .fd-next.problem { border-top-color:#d13438; }
  .fd-next-top { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
  .fd-next-top .fd-label { margin:0; }
  .fd-track { font-size:.85rem; color:var(--muted); font-weight:600; }
  .fd-ends { display:grid; grid-template-columns:minmax(0, 1fr) auto minmax(0, 1.4fr); gap:14px; align-items:center; }
  .fd-end small { display:block; font-size:.68rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); margin-bottom:2px; }
  .fd-end strong { display:block; font-size:1.2rem; line-height:1.25; overflow-wrap:anywhere; }
  .fd-end span { display:block; color:var(--muted); font-size:.88rem; overflow-wrap:anywhere; }
  .fd-end.to strong { font-size:1.4rem; }
  .fd-line { width:clamp(28px, 6vw, 90px); height:2px; background:repeating-linear-gradient(to right, var(--border) 0 6px, transparent 6px 11px); }
  .fd-due { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-top:16px; font-size:.95rem; font-weight:600; }
  .fd-due small { font-weight:400; color:var(--muted); font-size:.88rem; }
  .fd-stops { display:block; margin-top:10px; padding:10px 12px; border-radius:10px; background:var(--hover); color:var(--text); text-decoration:none; font-size:.9rem; }

  .fd-steps { display:grid; grid-template-columns:repeat(5, 1fr); margin:20px 0 4px; padding:0; list-style:none; }
  .fd-steps li { position:relative; text-align:center; font-size:.72rem; color:var(--muted); padding-top:20px; line-height:1.25; }
  .fd-steps li::before { content:''; position:absolute; top:5px; left:-50%; width:100%; height:2px; background:var(--border); }
  .fd-steps li:first-child::before { display:none; }
  .fd-steps li::after { content:''; position:absolute; top:0; left:50%; width:12px; height:12px; margin-left:-6px; border-radius:50%; background:var(--card); border:2px solid var(--border); box-sizing:border-box; }
  .fd-steps li.done::before, .fd-steps li.now::before { background:var(--primary); }
  .fd-steps li.done::after { background:var(--primary); border-color:var(--primary); }
  .fd-steps li.now { color:var(--text); font-weight:700; }
  .fd-steps li.now::after { border-color:var(--primary); box-shadow:0 0 0 4px color-mix(in srgb, var(--primary) 22%, transparent); }
  .fd-next.problem .fd-steps li.now::after { border-color:#d13438; box-shadow:0 0 0 4px rgba(209,52,56,.2); }

  .fd-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:20px; }
  .fd-actions .btn { padding:12px 18px; min-height:46px; font-weight:600; }
  .fd-actions .btn.primary { flex:1 1 200px; }
  .fd-share { display:inline-flex; align-items:center; gap:6px; margin-top:14px; font-size:.85rem; color:var(--muted); text-decoration:none; }
  .fd-share::before { content:''; width:8px; height:8px; border-radius:50%; background:var(--muted); }
  .fd-share.on { color:#16a34a; font-weight:600; }
  .fd-share.on::before { background:#16a34a; box-shadow:0 0 0 3px rgba(22,163,74,.2); }

  .fd-list { list-style:none; margin:0 -8px; padding:0; }
  .fd-list li + li { border-top:1px solid var(--border); }
  .fd-list a { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:12px 8px; border-radius:8px; color:var(--text); text-decoration:none; }
  .fd-list a:hover { background:var(--hover); }
  .fd-list small { display:block; color:var(--muted); font-size:.8rem; margin-top:2px; }
  .fd-list .fd-right { text-align:right; flex-shrink:0; }

  .fd-links { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; }
  .fd-links a { display:flex; align-items:center; gap:10px; padding:14px; border:1px solid var(--border); border-radius:12px; background:var(--card); color:var(--text); text-decoration:none; font-weight:600; font-size:.92rem; }
  .fd-links a:hover { background:var(--hover); border-color:var(--primary); }
  .fd-links svg { color:var(--primary); }

  .fd .fd-info { padding:4px 20px; }
  .fd-info details + details { border-top:1px solid var(--border); }
  .fd-info summary { padding:15px 0; cursor:pointer; font-weight:600; list-style:none; display:flex; justify-content:space-between; align-items:center; gap:10px; }
  .fd-info summary::-webkit-details-marker { display:none; }
  .fd-info summary::after { content:'+'; font-size:1.2rem; color:var(--muted); }
  .fd-info details[open] summary::after { content:'−'; }
  .fd-info .fd-body { padding:0 0 14px; font-size:.92rem; line-height:1.6; }
  .fd-info .fd-body ul { margin:0 0 8px; padding-left:20px; }
  .fd-info .fd-body li { margin-bottom:4px; }
  .fd-info .fd-body p { margin:0 0 8px; }
  .fd-status-pick { display:block; margin-bottom:6px; font-size:.8rem; color:var(--muted); }
  .fd-status-select { width:100%; padding:10px 12px; font-size:.95rem; }
  .fd-status-shown { margin-top:12px; padding:12px 14px; border-radius:10px; background:var(--hover); }
  .fd-status-shown p { margin:8px 0 0; color:var(--text); }

  @media (max-width:640px) {
    .fd .fd-hello { padding:20px; border-radius:14px; }
    .fd-hello h2 { font-size:1.3rem; }
    .fd-week { width:100%; }
    .fd .fd-next { padding:18px; }
    .fd-ends { grid-template-columns:minmax(0, 1fr); gap:10px; }
    .fd-line { display:none; }
    .fd-actions .btn { flex:1 1 calc(50% - 5px); }
    .fd-actions .btn.primary { flex-basis:100%; }
  }
</style>
@endpush

@section('content')
  <div class="fd">
    {{-- 1. Greeting, with this week's numbers --}}
    <section class="card fd-hello">
      <div>
        <h2>{{ $greeting }}, {{ Str::before($user->displayName(), ' ') ?: $user->displayName() }}!</h2>
        <p>
          {{ now()->translatedFormat('l, j F') }}
          @if ($driver)
            <span class="fd-due-pill">{{ $dueToday ? trans_choice(':count delivery due today|:count deliveries due today', $dueToday) : __('No deliveries due today') }}</span>
          @endif
        </p>
      </div>
      @if ($week)
        <dl class="fd-week" aria-label="{{ __('My week') }}">
          <div><dd>{{ $week['delivered'] }}</dd><dt>{{ __('delivered') }}</dt></div>
          <div><dd>{{ $week['on_time'] !== null ? $week['on_time'].'%' : '—' }}</dd><dt>{{ __('on time') }}</dt></div>
          <div class="{{ $week['delayed'] ? 'warn' : '' }}"><dd>{{ $week['delayed'] }}</dd><dt>{{ __('delayed now') }}</dt></div>
        </dl>
      @endif
    </section>

    {{-- 2. Unread alerts --}}
    @if ($unread)
      <section class="card fd-alerts">
        <span>🔔 {{ trans_choice('You have :count unread alert from the office.|You have :count unread alerts from the office.', $unread) }}</span>
        <button type="button" class="btn sm" onclick="document.getElementById('bellTrigger').click(); event.stopPropagation();">{{ __('View') }}</button>
      </section>
    @endif

    <div class="fd-main">
      @if (! $driver)
        {{-- Helpers, and drivers not yet linked, have no deliveries of their own yet. --}}
        <section class="card">
          <p class="fd-label">{{ __('My deliveries') }}</p>
          <p class="fd-empty">
            @if ($user->isHelper())
              {{ __("Your deliveries will appear here once you're assigned to a truck. Ask the office if you're going out today.") }}
            @else
              {{ __("Your account isn't linked to a driver record yet, so no deliveries can be shown. Ask a Manager to link it from the Drivers page.") }}
            @endif
          </p>
        </section>
      @elseif ($next)
        {{-- 3. Next delivery --}}
        <section class="card fd-next {{ $hasProblem ? 'problem' : '' }}">
          <div class="fd-next-top">
            <p class="fd-label">{{ __('Next delivery') }} <span class="fd-track">· {{ $next->tracking_number }}</span></p>
            <span class="badge {{ $next->badgeClass() }}">{{ __($next->statusLabel()) }}</span>
          </div>

          <div class="fd-ends">
            <div class="fd-end">
              <small>{{ __('From') }}</small>
              <strong>{{ $next->origin_name ?: $next->origin_city }}</strong>
              <span>{{ collect([$next->origin_address, $next->origin_city])->filter()->unique()->implode(', ') }}</span>
            </div>
            <div class="fd-line" aria-hidden="true"></div>
            <div class="fd-end to">
              <small>{{ __('To') }}</small>
              <strong>{{ $next->destination_name }}</strong>
              <span>{{ collect([$next->destination_address, $next->destination_city])->filter()->implode(', ') }}</span>
            </div>
          </div>

          <div class="fd-due">
            @if ($isOverdue) <span class="badge b-delayed">{{ __('Overdue') }}</span> @endif
            {{ __('Due :when, :time', [
              'when' => $next->scheduled_delivery_at->isToday() ? __('today') : $next->scheduled_delivery_at->translatedFormat('D, M j'),
              'time' => $next->scheduled_delivery_at->format('g:i A'),
            ]) }}
            <small>({{ $next->scheduled_delivery_at->diffForHumans() }})</small>
          </div>

          @if ($next->pickups->isNotEmpty())
            <a class="fd-stops" href="{{ route('shipments.show', $next) }}#pickups">
              <strong>{{ __(':done of :total collected', ['done' => $next->pickups->filter->isCollected()->count(), 'total' => $next->pickups->count()]) }}</strong>
              @if ($nextStop) · {{ __('Next pickup: :place', ['place' => $nextStop->sequence.' · '.$nextStop->name]) }} @endif
            </a>
          @endif

          <ol class="fd-steps" aria-label="{{ __('Status') }}: {{ __($next->statusLabel()) }}">
            @foreach ($journey as $i => $step)
              @php $at = $stepOf[$next->status] ?? -1; @endphp
              <li class="{{ $i < $at ? 'done' : ($i === $at ? 'now' : '') }}" @if ($i === $at) aria-current="step" @endif>{{ __(Shipment::label($step)) }}</li>
            @endforeach
          </ol>

          <div class="fd-actions">
            <a class="btn primary" href="{{ route('shipments.show', $next) }}{{ $canUpdate ? '#delivery-update' : '' }}">{{ $canUpdate ? __('Update delivery') : __('Open delivery') }}</a>
            @if ($canUpdate)
              <a class="btn" href="{{ route('shipments.show', $next) }}">{{ __('Open delivery') }}</a>
            @endif
            @if ($mapsTo)
              <a class="btn" href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($mapsTo) }}" target="_blank" rel="noopener">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $nextStop ? __('Directions to pickup') : __('Directions') }}
              </a>
            @endif
          </div>

          @if ($canShare)
            <a class="fd-share {{ $isSharing ? 'on' : '' }}" href="{{ route('shipments.show', $next) }}#share-location">{{ $isSharing ? __('Location sharing: On') : __('Location sharing: Off') }}</a>
          @endif
        </section>

        {{-- 4. Other open deliveries --}}
        <section class="card">
          <p class="fd-label">{{ __('My other deliveries (:count)', ['count' => $others->count()]) }}</p>
          @if ($others->isEmpty())
            <p class="fd-empty">{{ __('Nothing else assigned to you right now.') }}</p>
          @else
            <ul class="fd-list">
              @foreach ($others as $s)
                <li>
                  <a href="{{ route('shipments.show', $s) }}">
                    <span><strong>{{ $s->destination_name }}</strong><small>{{ $s->tracking_number }} · {{ $s->destination_city }}</small></span>
                    <span class="fd-right"><span class="badge {{ $s->badgeClass() }}">{{ __($s->statusLabel()) }}</span><small>{{ $s->scheduled_delivery_at->translatedFormat('M j, g:i A') }}</small></span>
                  </a>
                </li>
              @endforeach
            </ul>
          @endif
        </section>
      @else
        <section class="card">
          <p class="fd-label">{{ __('My deliveries') }}</p>
          <p class="fd-empty">{{ __("No open deliveries assigned to you. New ones will appear here, and you'll get an alert.") }}</p>
        </section>
      @endif
    </div>

    <div class="fd-side">
      {{-- 5. Shortcuts --}}
      <nav class="fd-links" aria-label="{{ __('Quick links') }}">
        <a href="{{ route('calendar.index') }}">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          {{ __('Calendar') }}
        </a>
        <a href="{{ route('shipments.index') }}">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
          {{ __('Shipments') }}
        </a>
      </nav>

      {{-- 6. What each delivery status means, in the order a delivery moves through them. --}}
      <section class="card fd-info">
        <details name="fd-info">
          <summary>{{ __('What each delivery status means') }}</summary>
          <div class="fd-body">
            {{-- One status at a time, so the list never pushes the page down. --}}
            <label class="fd-status-pick" for="fd-status-select">{{ __('Choose a status') }}</label>
            <select id="fd-status-select" class="fd-status-select">
              @foreach (Shipment::STATUSES as $s)
                <option value="{{ $s }}" data-badge="{{ Shipment::badge($s) }}" data-desc="{{ __(Shipment::description($s)) }}" @selected(($next?->status ?? 'pending') === $s)>{{ __(Shipment::label($s)) }}</option>
              @endforeach
            </select>
            <div class="fd-status-shown" aria-live="polite">
              <span class="badge" id="fd-status-badge"></span>
              <p id="fd-status-desc"></p>
            </div>
          </div>
        </details>
      </section>

      {{-- 7–9. Guide, guidelines and company profile, edited by a Super Admin (shown as written). --}}
      @if ($sections->isNotEmpty())
        <section class="card fd-info">
          @foreach ($sections as $key => $s)
            <details name="fd-info" @if ($key === 'guide' && ! $next) open @endif>
              <summary>{{ $s->title }}</summary>
              <div class="fd-body">{{ $s->bodyHtml() }}</div>
            </details>
          @endforeach
        </section>
      @endif
    </div>
  </div>
@endsection

@push('scripts')
<script>
  // The status guide shows the meaning of whichever status is picked in its dropdown.
  (function () {
    const select = document.getElementById('fd-status-select');
    if (!select) return;
    const badge = document.getElementById('fd-status-badge');
    const desc = document.getElementById('fd-status-desc');
    function show() {
      const option = select.selectedOptions[0];
      badge.className = 'badge ' + option.dataset.badge;
      badge.textContent = option.textContent;
      desc.textContent = option.dataset.desc;
    }
    select.addEventListener('change', show);
    show();
  })();

  // One guide open at a time: opening one closes the others. Newer browsers do this from
  // the shared name on <details>; this covers the ones that don't.
  document.querySelectorAll('.fd-info details').forEach((opened, _, all) => {
    opened.addEventListener('toggle', () => {
      if (opened.open) all.forEach((other) => { if (other !== opened) other.open = false; });
    });
  });
</script>
@endpush
