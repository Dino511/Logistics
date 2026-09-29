@extends('layouts.app')
@section('title', __('My dashboard'))
@section('heading', __('My dashboard'))

@push('head')
<style>
  .fd { max-width:860px; }
  .fd > .card { margin-bottom:16px; }
  .fd-hello { padding:22px 24px; background:linear-gradient(135deg, color-mix(in srgb, var(--primary) 16%, var(--card)), var(--card)); }
  .fd-hello h2 { margin:0 0 4px; font-size:1.35rem; }
  .fd-hello p { margin:0; color:var(--muted); font-size:.92rem; }
  .fd-label { margin:0 0 10px; font-size:.7rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); }
  .fd-next { border-left:4px solid var(--primary); }
  .fd-next.delayed { border-left-color:#d13438; }
  .fd-next-top { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
  .fd-next-top strong { font-size:1.05rem; }
  .fd-route { margin:10px 0 4px; font-size:1.02rem; font-weight:600; }
  .fd-due { color:var(--muted); font-size:.88rem; }
  .fd-next-actions { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-top:14px; }
  .fd-next-actions .btn { padding:11px 20px; text-decoration:none; }
  .fd-share { font-size:.85rem; color:var(--muted); }
  .fd-share.on { color:#16a34a; font-weight:600; }
  .fd-list { list-style:none; margin:0; padding:0; }
  .fd-list li + li { border-top:1px solid var(--border); }
  .fd-list a { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:12px 4px; color:var(--text); text-decoration:none; }
  .fd-list a:hover { background:var(--hover); }
  .fd-list small { display:block; color:var(--muted); font-size:.8rem; margin-top:2px; }
  .fd-list .fd-right { text-align:right; flex-shrink:0; }
  .fd-empty { color:var(--muted); font-size:.9rem; margin:0; }
  .fd-alerts { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:14px 18px; background:color-mix(in srgb, var(--primary) 10%, var(--card)); }
  .fd-week { display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; text-align:center; }
  .fd-week strong { display:block; font-size:1.5rem; }
  .fd-week span { color:var(--muted); font-size:.8rem; }
  .fd-info { padding:4px 18px; }
  .fd-info details + details { border-top:1px solid var(--border); }
  .fd-info summary { padding:14px 0; cursor:pointer; font-weight:600; list-style:none; display:flex; justify-content:space-between; align-items:center; }
  .fd-info summary::-webkit-details-marker { display:none; }
  .fd-info summary::after { content:'+'; font-size:1.2rem; color:var(--muted); }
  .fd-info details[open] summary::after { content:'−'; }
  .fd-info .fd-body { padding:0 0 14px; font-size:.92rem; line-height:1.6; }
  .fd-info .fd-body ul { margin:0 0 8px; padding-left:20px; }
  .fd-info .fd-body li { margin-bottom:4px; }
  .fd-info .fd-body p { margin:0 0 8px; }
  @media (max-width:640px) { .fd-hello { padding:18px; } .fd-next-actions .btn { width:100%; text-align:center; } }
</style>
@endpush

@section('content')
  <div class="fd">
    {{-- 1. Greeting --}}
    <section class="card fd-hello">
      <h2>{{ $greeting }}, {{ Str::before($user->name, ' ') ?: $user->name }}!</h2>
      <p>
        {{ now()->translatedFormat('l, j F') }}
        @if ($driver)
          · {{ $dueToday ? trans_choice(':count delivery due today|:count deliveries due today', $dueToday) : __('No deliveries due today') }}
        @endif
      </p>
    </section>

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
      {{-- 2. Next delivery --}}
      <section class="card fd-next {{ $next->status === 'delayed' ? 'delayed' : '' }}">
        <p class="fd-label">{{ __('Next delivery') }}</p>
        <div class="fd-next-top">
          <strong>{{ $next->tracking_number }}</strong>
          <span class="badge {{ $next->badgeClass() }}">{{ __($next->statusLabel()) }}</span>
        </div>
        <div class="fd-route">{{ $next->origin_city }} → {{ $next->destination_name }}, {{ $next->destination_city }}</div>
        <div class="fd-due">
          {{ __('Due :when, :time', [
            'when' => $next->scheduled_delivery_at->isToday() ? __('today') : $next->scheduled_delivery_at->translatedFormat('D, M j'),
            'time' => $next->scheduled_delivery_at->format('g:i A'),
          ]) }}
        </div>
        <div class="fd-next-actions">
          <a class="btn primary" href="{{ route('shipments.show', $next) }}">{{ __('Open delivery') }}</a>
          @if (in_array($next->status, \App\Http\Controllers\TrackingController::TRACKABLE_STATUSES, true))
            <span class="fd-share {{ $isSharing ? 'on' : '' }}">📍 {{ $isSharing ? __('Location sharing: On') : __('Location sharing: Off') }}</span>
          @endif
        </div>
      </section>

      {{-- 3. Other open deliveries --}}
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

    {{-- 4. Unread alerts --}}
    @if ($unread)
      <section class="card fd-alerts">
        <span>🔔 {{ trans_choice('You have :count unread alert from the office.|You have :count unread alerts from the office.', $unread) }}</span>
        <button type="button" class="btn sm" onclick="document.getElementById('bellTrigger').click(); event.stopPropagation();">{{ __('View') }}</button>
      </section>
    @endif

    {{-- 5. This week --}}
    @if ($week)
      <section class="card">
        <p class="fd-label">{{ __('My week') }}</p>
        <div class="fd-week">
          <div><strong>{{ $week['delivered'] }}</strong><span>{{ __('delivered') }}</span></div>
          <div><strong>{{ $week['delayed'] }}</strong><span>{{ __('delayed now') }}</span></div>
          <div><strong>{{ $week['on_time'] !== null ? $week['on_time'].'%' : '—' }}</strong><span>{{ __('on time') }}</span></div>
        </div>
      </section>
    @endif

    {{-- 6–8. Guide, guidelines and company profile, edited by a Super Admin (shown as written). --}}
    @if ($sections->isNotEmpty())
      <section class="card fd-info">
        @foreach ($sections as $key => $s)
          <details @if ($key === 'guide' && ! $next) open @endif>
            <summary>{{ $s->title }}</summary>
            <div class="fd-body">{{ $s->bodyHtml() }}</div>
          </details>
        @endforeach
      </section>
    @endif
  </div>
@endsection
