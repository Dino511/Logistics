<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logistics – @yield('title')</title>
@include('partials.pwa')
<script>
  // Apply saved theme before paint to avoid a light-mode flash.
  try {
    var t = localStorage.getItem('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.dataset.theme = t;
  } catch (e) {}
</script>
@stack('head')
<style>
  :root { --bg:#f3f5f9; --card:#fff; --text:#1b2333; --muted:#6b7690; --border:#e2e7f0; --primary:#2454e6; --side:#12224d; --hover:#eef1f8; }
  :root[data-theme="dark"] { --bg:#0e1424; --card:#171f35; --text:#e8ecf6; --muted:#9aa6c4; --border:#2a3556; --primary:#4f7bff; --side:#0a1230; --hover:#222c48; color-scheme:dark; }
  [data-theme="dark"] .b-transit { background:#1d2c5c; color:#8fb0ff; } [data-theme="dark"] .b-delivered { background:#143524; color:#5fd68f; }
  [data-theme="dark"] .b-delayed { background:#45191b; color:#ff8a8d; } [data-theme="dark"] .b-pending { background:#2a3350; color:#9aa6c4; }
  body, .card, aside { transition: background-color .2s, color .2s, border-color .2s; }

  .user-menu { position:relative; }
  .header-actions { display:flex; align-items:center; gap:6px; }
  .lang-row { display:flex !important; align-items:center; justify-content:space-between; gap:10px; padding:8px 12px; font-size:.9rem; color:var(--text); }
  .lang-choices { display:flex; border:1px solid var(--border); border-radius:8px; overflow:hidden; }
  header .lang-btn { padding:5px 10px; border:0; border-radius:0; background:transparent; color:var(--muted); font:inherit; font-size:.8rem; cursor:pointer; }
  header .lang-btn[aria-pressed="true"] { background:var(--primary); color:#fff; font-weight:600; }
  .bell-menu { position:relative; }
  header .bell-btn { position:relative; display:grid; place-items:center; width:40px; height:40px; padding:0; border:1px solid transparent; border-radius:10px; background:transparent; color:var(--text); cursor:pointer; }
  header .bell-btn:hover, header .bell-btn[aria-expanded="true"] { background:var(--hover); border-color:var(--border); }
  .bell-count { position:absolute; top:3px; right:2px; min-width:18px; height:18px; padding:0 5px; border-radius:99px; background:#d13438; color:#fff; font-size:.68rem; font-weight:700; line-height:18px; text-align:center; }
  .bell-panel { width:340px; max-width:calc(100vw - 32px); padding:0; overflow:hidden; }
  .bell-head { display:flex; justify-content:space-between; align-items:center; padding:12px 14px; border-bottom:1px solid var(--border); }
  header .bell-readall { padding:0; border:0; background:none; color:var(--primary); font:inherit; font-size:.8rem; cursor:pointer; }
  .bell-list { list-style:none; margin:0; padding:4px; max-height:360px; overflow-y:auto; }
  .bell-list a { display:block; padding:10px 12px; border-radius:8px; color:var(--text); text-decoration:none; font-size:.86rem; }
  .bell-list a:hover { background:var(--hover); }
  .bell-list a.unread { font-weight:600; }
  .bell-list a.unread::before { content:''; display:inline-block; width:7px; height:7px; border-radius:50%; background:var(--primary); margin-right:7px; vertical-align:middle; }
  .bell-list small { display:block; margin-top:2px; color:var(--muted); font-weight:400; font-size:.74rem; }
  .bell-empty { padding:18px 12px; text-align:center; color:var(--muted); font-size:.86rem; }
  .alert-toasts { position:fixed; right:16px; bottom:16px; z-index:100; display:flex; flex-direction:column; gap:8px; width:320px; max-width:calc(100vw - 32px); }
  .alert-toast { display:block; padding:12px 14px; border-radius:10px; background:var(--card); color:var(--text); border:1px solid var(--border); border-left:4px solid var(--primary); box-shadow:0 10px 30px rgba(0,0,0,.25); text-decoration:none; font-size:.86rem; animation:toast-in .2s ease-out; transition:opacity .3s, transform .3s; }
  .alert-toast strong { display:block; font-size:.72rem; letter-spacing:.06em; text-transform:uppercase; color:var(--primary); margin-bottom:2px; }
  .alert-toast.leaving { opacity:0; transform:translateY(8px); }
  @keyframes toast-in { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
  .alert-toast.sos { border-left-color:#d13438; background:#fff1f1; color:#7a1113; }
  [data-theme="dark"] .alert-toast.sos { background:#3a1416; color:#ffd7d8; }
  .alert-toast.sos strong { color:#d13438; }
  .bell-list a.sos { color:#d13438; }
  /* Drivers' Emergency button and panel: large tap targets for use on a phone. */
  /* Centred under the sidebar menu (the sidebar is 220px wide)… */
  .sos-fab { position:fixed; left:110px; bottom:20px; transform:translateX(-50%); z-index:90; display:flex; align-items:center; gap:8px; padding:12px 22px; border:0; border-radius:99px; background:#d13438; color:#fff; font:inherit; font-weight:700; font-size:.95rem; white-space:nowrap; box-shadow:0 8px 24px rgba(209,52,56,.45); cursor:pointer; }
  .sos-fab:hover { background:#b52a2e; }
  .sos-fab:focus-visible { outline:2px solid #fff; outline-offset:3px; }
  /* …or of the screen on phones, where the sidebar is hidden. */
  @media (max-width:760px) { .sos-fab { left:50%; bottom:16px; } body:has(.sos-fab) main { padding-bottom:84px; } }
  .sos-box { max-width:420px; padding:18px; }
  .sos-head { display:flex; justify-content:space-between; align-items:center; }
  .sos-head h2 { margin:0; color:#d13438; }
  .sos-close { width:36px; height:36px; border:0; border-radius:50%; background:var(--hover); color:var(--text); font-size:1.4rem; line-height:1; cursor:pointer; }
  .sos-warning { margin:10px 0 4px; padding:10px 12px; border-radius:10px; background:#fff1f1; color:#7a1113; font-size:.9rem; }
  [data-theme="dark"] .sos-warning { background:#3a1416; color:#ffd7d8; }
  .sos-group { margin:14px 0 6px; font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); }
  .sos-call { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 14px; margin-bottom:6px; border:1px solid var(--border); border-radius:12px; color:var(--text); text-decoration:none; }
  .sos-call:hover { background:var(--hover); }
  .sos-call small { display:block; color:var(--muted); font-size:.85rem; margin-top:1px; }
  .sos-call-btn { flex-shrink:0; padding:8px 16px; border-radius:99px; background:#16a34a; color:#fff; font-weight:700; font-size:.9rem; }
  .sos-call.urgent { border-color:#d13438; }
  .sos-call.urgent .sos-call-btn { background:#d13438; }
  .sos-form input { width:100%; padding:10px 12px; margin-bottom:8px; border:1px solid var(--border); border-radius:10px; background:transparent; color:var(--text); font:inherit; }
  .sos-send { width:100%; padding:13px; border:0; border-radius:12px; background:#d13438; color:#fff; font:inherit; font-weight:700; font-size:1rem; cursor:pointer; }
  .sos-form small { display:block; margin-top:6px; color:var(--muted); font-size:.78rem; text-align:center; }
  @media print { .alert-toasts, .sos-fab { display:none; } }
  .user-trigger { display:flex; align-items:center; gap:10px; padding:6px 10px; background:transparent; border:1px solid transparent; border-radius:10px; color:var(--text); cursor:pointer; font:inherit; }
  .user-trigger:hover, .user-trigger[aria-expanded="true"] { background:var(--hover); border-color:var(--border); }
  .avatar { width:34px; height:34px; border-radius:50%; background:var(--primary); color:#fff; display:grid; place-items:center; font-weight:700; font-size:.85rem; }
  .user-info { text-align:left; line-height:1.2; }
  .user-info strong { display:block; font-size:.9rem; } .user-info small { color:var(--muted); font-size:.75rem; }
  .chevron { transition:transform .15s; color:var(--muted); } .user-trigger[aria-expanded="true"] .chevron { transform:rotate(180deg); }
  .dropdown { position:absolute; right:0; top:calc(100% + 8px); min-width:230px; background:var(--card); border:1px solid var(--border); border-radius:12px; padding:6px; box-shadow:0 10px 30px rgba(0,0,0,.15); z-index:50; }
  .dropdown[hidden] { display:none; }
  .dropdown .item { display:flex; align-items:center; justify-content:space-between; gap:12px; width:100%; padding:10px 12px; border:0; background:none; border-radius:8px; color:var(--text); font:inherit; font-size:.9rem; cursor:pointer; text-align:left; }
  .dropdown .item:hover, .dropdown .item:focus-visible { background:var(--hover); outline:none; }
  .dropdown .item.danger { color:#d13438; } [data-theme="dark"] .dropdown .item.danger { color:#ff6b6f; }
  .dropdown hr { border:0; border-top:1px solid var(--border); margin:6px 0; }
  .dropdown form { display:block; margin:0; }
  .switch { width:38px; height:22px; border-radius:99px; background:var(--border); position:relative; flex-shrink:0; transition:background .2s; }
  .switch::after { content:""; position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%; background:#fff; transition:transform .2s; }
  [aria-checked="true"] .switch { background:var(--primary); } [aria-checked="true"] .switch::after { transform:translateX(16px); }
  @media (max-width:520px) { .user-info { display:none; } }
  .avatar { overflow:hidden; flex-shrink:0; } .avatar img { width:100%; height:100%; object-fit:cover; display:block; }

  .modal { position:fixed; inset:0; background:rgba(8,12,24,.55); display:flex; align-items:center; justify-content:center; padding:16px; z-index:100; }
  .modal[hidden] { display:none; }
  .modal-box { width:100%; max-width:440px; max-height:100%; overflow:auto; background:var(--card); border:1px solid var(--border); border-radius:14px; padding:24px; }
  .modal-box h2 { margin:0 0 18px; }
  .modal-box label { display:block; font-size:.85rem; font-weight:600; margin-bottom:6px; }
  .modal-box input[type=text], .modal-box input[type=email] { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font-size:1rem; margin-bottom:16px; }
  .modal-box input:focus { outline:2px solid var(--primary); outline-offset:1px; }
  .photo-row { display:flex; align-items:center; gap:16px; margin-bottom:16px; }
  .photo-row .avatar { width:72px; height:72px; font-size:1.4rem; }
  .photo-row small { display:block; color:var(--muted); margin-top:4px; }
  .form-errors { color:#d13438; font-size:.85rem; margin:0 0 14px; padding-left:18px; }
  .modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:8px; }
  .btn { padding:9px 16px; border-radius:8px; border:1px solid var(--border); background:var(--card); color:var(--text); cursor:pointer; font:inherit; }
  .btn.primary { background:var(--primary); border-color:var(--primary); color:#fff; font-weight:600; }
  * { box-sizing:border-box; }
  body { margin:0; display:flex; min-height:100vh; background:var(--bg); color:var(--text); font-family:system-ui,"Segoe UI",sans-serif; }
  /* Artwork under a dark tint so the nav links stay readable; the right edge darkens to meet the content area. */
  aside { width:220px; background:linear-gradient(to bottom, rgba(10,20,50,.82), rgba(10,20,50,.72) 45%, rgba(10,20,50,.9)), linear-gradient(to right, transparent 80%, rgba(10,20,50,.4)), var(--side) url('{{ \App\Models\SiteImage::urlFor('login_background_portrait') }}') center / cover no-repeat; box-shadow:inset -1px 0 0 rgba(255,255,255,.06); color:#fff; padding:24px 16px; flex-shrink:0; position:sticky; top:0; height:100vh; overflow-y:auto; align-self:flex-start; }
  aside a { display:block; padding:10px 12px; border-radius:8px; color:#dfe5f7; text-shadow:0 1px 4px rgba(0,0,0,.5); text-decoration:none; margin-bottom:4px; }
  aside a.active, aside a:hover { background:rgba(255,255,255,.12); color:#fff; }
  aside .logo { display:flex; align-items:center; gap:8px; font-weight:700; font-size:1.2rem; margin-bottom:24px; color:#fff; }
  aside .nav-group + .nav-group { margin-top:8px; padding-top:8px; border-top:1px solid rgba(255,255,255,.12); }
  aside .nav-label { margin:0 0 4px; padding:0 12px; font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#aebbe0; text-shadow:0 1px 4px rgba(0,0,0,.6); }
  /* Collapsible menu sections */
  aside .nav-toggle { display:flex; justify-content:space-between; align-items:center; width:100%; padding:7px 12px; border:0; border-radius:6px; background:none; font-family:inherit; cursor:pointer; }
  aside .nav-toggle:hover { color:#fff; background:rgba(255,255,255,.06); }
  aside .nav-toggle:focus-visible { outline:2px solid #fff; outline-offset:1px; }
  aside .nav-chevron { transition:transform .2s ease; flex-shrink:0; }
  aside .nav-group.collapsed .nav-chevron { transform:rotate(-90deg); }
  aside .nav-items { display:grid; grid-template-rows:1fr; transition:grid-template-rows .2s ease; }
  aside .nav-items > div { overflow:hidden; }
  aside .nav-group.collapsed .nav-items { grid-template-rows:0fr; }
  aside .nav-group.collapsed .nav-items > div { visibility:hidden; transition:visibility 0s .2s; } /* no tabbing into hidden links */
  aside .nav-group.current .nav-toggle { color:#fff; }
  main { flex:1; padding:24px; min-width:0; }
  .open-banner { display:flex; align-items:center; gap:12px; padding:10px 14px; margin-bottom:16px; border-radius:10px; text-decoration:none; color:var(--text); font-size:.9rem;
    border:1px solid color-mix(in srgb, var(--primary) 35%, var(--border)); background:color-mix(in srgb, var(--primary) 9%, var(--card)); }
  .open-banner:hover { background:color-mix(in srgb, var(--primary) 15%, var(--card)); }
  .open-banner.overdue { border-color:#d13438; background:color-mix(in srgb, #d13438 9%, var(--card)); }
  .open-banner-text { flex:1; min-width:0; }
  .open-banner-text strong { margin-right:6px; }
  .open-banner-go { flex-shrink:0; font-weight:600; color:var(--primary); }
  @media print { .open-banner { display:none; } }
  header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; gap:12px; }
  header h1 { margin:0; font-size:1.5rem; }
  header form { display:flex; align-items:center; gap:12px; font-size:.9rem; }
  header button { padding:8px 14px; border:1px solid var(--border); background:var(--card); border-radius:8px; cursor:pointer; }
  .card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:20px; }
  h2 { margin:0 0 16px; font-size:1.1rem; }
  table { width:100%; border-collapse:collapse; font-size:.9rem; }
  th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--border); white-space:nowrap; }
  th { color:var(--muted); font-weight:600; font-size:.8rem; }
  .table-wrap { overflow-x:auto; }
  .badge { padding:3px 10px; border-radius:99px; font-size:.78rem; font-weight:600; }
  .b-transit { background:#e3ebff; color:#2454e6; } .b-delivered { background:#dcf5e6; color:#1a8a4a; }
  .b-delayed { background:#fde3e4; color:#d13438; } .b-pending { background:#eef0f5; color:#6b7690; }
  @media (max-width:760px) { aside { display:none; } }
  .btn.sm { padding:5px 10px; font-size:.8rem; }
  @media print {
    /* Ctrl+P on any page: paper gets the content only, in light colours. */
    aside, header, .modal, .alert, .no-print { display:none !important; }
    body { display:block; background:#fff !important; color:#000 !important; }
    main { padding:0 !important; }
    .card { border:0 !important; padding:0 !important; }
  }
  select { padding:6px 8px; border:1px solid var(--border); border-radius:8px; background:var(--card); color:var(--text); font:inherit; font-size:.85rem; }
  /* The closed <select> box takes its colour from --card/--text above, but the dropdown's
     option list is rendered by the OS/browser and ignores that unless <option> is styled
     too — without this it falls back to a white popup, making light (dark-mode) text
     unreadable. Applies to every <select> in the app, including ones styled by other pages. */
  option { background-color: var(--card); color: var(--text); }
  .b-inactive { background:#eef0f5; color:#6b7690; } [data-theme="dark"] .b-inactive { background:#2a3350; color:#9aa6c4; }
  .b-active { background:#dcf5e6; color:#1a8a4a; } [data-theme="dark"] .b-active { background:#143524; color:#5fd68f; }
  .inline { display:inline-flex; gap:6px; align-items:center; margin:0; }
</style>
</head>
<body>
  <aside>
    <a class="logo {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" aria-label="Logistics – go to dashboard">🚚 Logistics</a>
    @php
      $u = auth()->user();
      $isOffice = $u->isSuperAdmin() || $u->hasRole('manager', 'logistics_coordinator');
      $isManager = $u->isSuperAdmin() || $u->hasRole('manager');
    @endphp
    @php
      // Menu sections and links, each shown only to the roles that can open it.
      $navGroups = array_filter([
        'operations' => ['label' => __('Operations'), 'links' => array_filter([
          ['shipments.*', route('shipments.index'), __('Shipments')],
          ['calendar.*', route('calendar.index'), __('Calendar')],
          $isOffice ? ['tracking.*', route('tracking.index'), __('Live tracking')] : null,
        ])],
        'fleet' => $isOffice ? ['label' => __('Fleet'), 'links' => [
          ['vehicles.*', route('vehicles.index'), __('Vehicles')],
          ['drivers.*', route('drivers.index'), __('Drivers')],
        ]] : null,
        'insights' => $isManager ? ['label' => __('Insights'), 'links' => [
          ['reports.*', route('reports.activity'), __('Reports')],
        ]] : null,
        // Setup pages. Managers see only Emergency contacts here.
        'admin' => $isManager ? ['label' => __('Administration'), 'links' => array_filter([
          $u->isSuperAdmin() ? ['users.*', route('users.index'), __('Users & Roles')] : null,
          ['emergency-contacts.*', route('emergency-contacts.index'), __('Emergency contacts')],
          $u->isSuperAdmin() ? ['site-contents.*', route('site-contents.index'), __('Driver dashboard')] : null,
          $u->isSuperAdmin() ? ['site-images.*', route('site-images.index'), __('Site Images')] : null,
        ])] : null,
      ]);
    @endphp
    <nav aria-label="Main" id="mainNav">
      @foreach ($navGroups as $key => $group)
        @php $current = collect($group['links'])->contains(fn ($l) => request()->routeIs($l[0])); @endphp
        {{-- The section with the current page is always open; others remember their state. --}}
        <div class="nav-group {{ $current ? 'current' : '' }}" data-group="{{ $key }}">
          <button type="button" class="nav-label nav-toggle" aria-expanded="true" aria-controls="nav-{{ $key }}" @if (count($navGroups) === 1) hidden @endif>
            <span>{{ $group['label'] }}</span>
            <svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </button>
          <div class="nav-items" id="nav-{{ $key }}">
            <div>
              @foreach ($group['links'] as [$pattern, $url, $text])
                <a class="{{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ $url }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $text }}</a>
              @endforeach
            </div>
          </div>
        </div>
      @endforeach
    </nav>
    <script>
      // Runs before the menu is drawn, so saved open/closed sections don't flicker.
      (function () {
        let saved = {};
        try { saved = JSON.parse(localStorage.getItem('navGroups') || '{}'); } catch (e) {}
        const set = (group, open) => {
          group.classList.toggle('collapsed', !open);
          group.querySelector('.nav-toggle').setAttribute('aria-expanded', String(open));
        };
        document.querySelectorAll('#mainNav .nav-group').forEach((group) => {
          const key = group.dataset.group;
          set(group, group.classList.contains('current') || saved[key] !== false);
          group.querySelector('.nav-toggle').addEventListener('click', () => {
            const open = group.classList.contains('collapsed');
            set(group, open);
            saved[key] = open;
            try { localStorage.setItem('navGroups', JSON.stringify(saved)); } catch (e) {}
          });
        });
      })();
    </script>
  </aside>

  <main>
    <header>
      <h1>@yield('heading')</h1>
      <div class="header-actions">
      <div class="bell-menu" id="bellMenu">
        <button type="button" class="bell-btn" id="bellTrigger" aria-haspopup="true" aria-expanded="false" aria-controls="bellPanel" aria-label="{{ __('Alerts') }}">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
          <span class="bell-count" id="bellCount" hidden></span>
        </button>
        <div class="dropdown bell-panel" id="bellPanel" hidden>
          <div class="bell-head">
            <strong>{{ __('Alerts') }}</strong>
            <button type="button" class="bell-readall" id="bellReadAll">{{ __('Mark all read') }}</button>
          </div>
          <ul class="bell-list" id="bellList"><li class="bell-empty">{{ __('Loading…') }}</li></ul>
        </div>
      </div>
      <div class="user-menu" id="userMenu">
        <button type="button" class="user-trigger" id="userTrigger" aria-haspopup="menu" aria-expanded="false" aria-controls="userDropdown">
          <span class="avatar">
            @if (auth()->user()->avatarUrl())
              <img src="{{ auth()->user()->avatarUrl() }}" alt="">
            @else
              {{ auth()->user()->initials() }}
            @endif
          </span>
          <span class="user-info">
            <strong>{{ auth()->user()->name }}</strong>
            <small>{{ auth()->user()->roleLabel(true) }}</small>
          </span>
          <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <div class="dropdown" id="userDropdown" role="menu" hidden>
          <button type="button" class="item" id="openProfile" role="menuitem">{{ __('Edit profile & photo') }}</button>
          <button type="button" class="item" id="themeToggle" role="menuitemcheckbox" aria-checked="false">
            <span id="themeLabel">{{ __('Dark mode') }}</span>
            <span class="switch" aria-hidden="true"></span>
          </button>
          <form method="POST" action="{{ route('locale.update') }}" class="lang-row" role="group" aria-label="{{ __('Language') }}">
            @csrf
            <span>🌐 {{ __('Language') }}</span>
            <span class="lang-choices">
              @foreach (\App\Http\Middleware\SetLocale::LOCALES as $code => $name)
                <button type="submit" name="locale" value="{{ $code }}" class="lang-btn" aria-pressed="{{ app()->getLocale() === $code ? 'true' : 'false' }}">{{ $name }}</button>
              @endforeach
            </span>
          </form>
          <hr>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="item danger" role="menuitem">{{ __('Sign out') }}</button>
          </form>
        </div>
      </div>
      </div>
    </header>

    {{-- Stays on every page while any delivery is unfinished; gone once they all are. --}}
    @if ($open = app(\App\Services\OpenShipments::class)->reminderFor(auth()->user()))
      <a class="open-banner {{ $open['overdue'] ? 'overdue' : '' }}" href="{{ $open['url'] }}" role="status">
        <span class="open-banner-icon" aria-hidden="true">🚚</span>
        <span class="open-banner-text">
          @if ($open['count'] === 1)
            <strong>{{ __('1 shipment is not delivered yet') }}</strong>
            {{ $open['first']->tracking_number }} · {{ __($open['first']->statusLabel()) }} · {{ __('due :when', ['when' => $open['first']->scheduled_delivery_at->translatedFormat('M j, g:i A')]) }}
          @else
            <strong>{{ __(':count shipments are not delivered yet', ['count' => $open['count']]) }}</strong>
            {{ __('Next due: :tracking, :when', ['tracking' => $open['first']->tracking_number, 'when' => $open['first']->scheduled_delivery_at->translatedFormat('M j, g:i A')]) }}
          @endif
          @if ($open['overdue']) <span class="badge b-delayed">{{ trans_choice(':count overdue|:count overdue', $open['overdue']) }}</span> @endif
        </span>
        <span class="open-banner-go">{{ __('View') }}</span>
      </a>
    @endif

    @foreach (['status' => 'success', 'warning' => 'warning', 'error' => 'error'] as $key => $type)
      @if (session($key))
        <x-alert :type="$type">{{ session($key) }}</x-alert>
      @endif
    @endforeach

    @yield('content')
  </main>

  @if (auth()->user()->hasRole('field_personnel'))
    @php
      $emergencyContacts = \App\Models\EmergencyContact::grouped();
      // On a shipment page, an SOS is about that shipment.
      $sosShipment = request()->routeIs('shipments.show') ? request()->route('shipment') : null;
    @endphp
    {{-- Drivers' Emergency button: centred at the bottom of the sidebar, or of the screen on phones. Opens #sosPanel. --}}
    <button type="button" class="sos-fab" id="sosOpen" aria-haspopup="dialog" aria-controls="sosPanel">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      {{ __('Emergency') }}
    </button>

    <div class="modal" id="sosPanel" role="dialog" aria-modal="true" aria-labelledby="sosTitle" hidden>
      <div class="modal-box sos-box">
        <div class="sos-head">
          <h2 id="sosTitle">{{ __('Emergency') }}</h2>
          <button type="button" class="sos-close" id="sosClose" aria-label="{{ __('Close') }}">&times;</button>
        </div>
        <p class="sos-warning">{!! __('If anyone is hurt or in danger, <strong>call 911 first</strong>.') !!}</p>

        @forelse ($emergencyContacts as $category => $contacts)
          <p class="sos-group">{{ \App\Models\EmergencyContact::CATEGORIES[$category] ?? ucfirst($category) }}</p>
          @foreach ($contacts as $c)
            <a class="sos-call {{ $category === 'emergency' ? 'urgent' : '' }}" href="{{ $c->telHref() }}">
              <span><strong>{{ $c->name }}</strong><small>{{ $c->phone }}</small></span>
              <span class="sos-call-btn" aria-hidden="true">{{ __('Call') }}</span>
            </a>
          @endforeach
        @empty
          <p class="sos-group">{{ __('No contacts have been added yet.') }}</p>
        @endforelse

        <form method="POST" action="{{ route('sos.store') }}" class="sos-form" onsubmit="return confirm(@js(__('Send an SOS alert to the office now?')))">
          @csrf
          @if ($sosShipment)<input type="hidden" name="shipment_id" value="{{ $sosShipment->shipment_id }}">@endif
          <p class="sos-group">{{ __('Alert the office') }}</p>
          <input type="text" name="message" maxlength="300" placeholder="{{ __("What's happening? (optional)") }}" aria-label="{{ __("What's happening") }}">
          <button type="submit" class="sos-send">🚨 {{ __('Send SOS') }}</button>
          <small>{{ __('Alerts managers with your name, delivery and last shared location. It does not call 911.') }}</small>
        </form>
      </div>
    </div>
  @endif

  <div class="modal" id="profileModal" role="dialog" aria-modal="true" aria-labelledby="profileTitle" hidden>
    <form class="modal-box" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <h2 id="profileTitle">{{ __('Edit profile') }}</h2>

      @if ($errors->profile->any())
        <ul class="form-errors">
          @foreach ($errors->profile->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
      @endif

      <div class="photo-row">
        <span class="avatar" id="previewAvatar">
          @if (auth()->user()->avatarUrl())
            <img src="{{ auth()->user()->avatarUrl() }}" alt="">
          @else
            {{ auth()->user()->initials() }}
          @endif
        </span>
        <div>
          <input type="file" name="avatar" id="avatarInput" accept="image/png,image/jpeg,image/webp">
          <small>{{ __('JPG, PNG or WebP, up to 2 MB.') }}</small>
          @if (auth()->user()->avatarUrl())
            <label style="margin:6px 0 0;font-weight:400"><input type="checkbox" name="remove_avatar" value="1"> {{ __('Remove current photo') }}</label>
          @endif
        </div>
      </div>

      <label for="pname">{{ __('Name') }}</label>
      <input type="text" id="pname" name="name" value="{{ old('name', auth()->user()->name) }}" required>
      <label for="pemail">{{ __('Email') }}</label>
      <input type="email" id="pemail" name="email" value="{{ old('email', auth()->user()->email) }}" required>

      <div class="modal-actions">
        <button type="button" class="btn" id="closeProfile">{{ __('Cancel') }}</button>
        <button type="submit" class="btn primary">{{ __('Save changes') }}</button>
      </div>
    </form>
  </div>

<script>
  // --- User dropdown + theme toggle ---
  const menu = document.getElementById('userMenu');
  const trigger = document.getElementById('userTrigger');
  const dropdown = document.getElementById('userDropdown');
  const themeToggle = document.getElementById('themeToggle');

  function setOpen(open) {
    dropdown.hidden = !open;
    trigger.setAttribute('aria-expanded', open);
    if (open) themeToggle.focus();
  }
  trigger.addEventListener('click', () => setOpen(dropdown.hidden));
  document.addEventListener('click', (e) => { if (!menu.contains(e.target)) setOpen(false); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !dropdown.hidden) { setOpen(false); trigger.focus(); }
  });

  // --- Alerts bell: unread count and latest alerts ---
  // Checked every 10 seconds while the page is visible, and straight away when the
  // tab regains focus. New unread alerts also pop up briefly in the corner.
  (function () {
    const POLL_MS = 10000;
    const T = {{ Js::from(['sosUrgent' => __('SOS – urgent'), 'newAlert' => __('New alert'), 'alerts' => __('Alerts'), 'alertsUnread' => __('Alerts, :count unread'), 'noAlerts' => __('No alerts yet.')]) }};
    const bellMenu = document.getElementById('bellMenu');
    const bellBtn = document.getElementById('bellTrigger');
    const panel = document.getElementById('bellPanel');
    const list = document.getElementById('bellList');
    const count = document.getElementById('bellCount');
    const token = @json(csrf_token());
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // Newest alert id already seen, so only genuinely new ones pop up (none on first load).
    let newestSeen = null;

    function toast(a) {
      let box = document.getElementById('alertToasts');
      if (!box) {
        box = document.createElement('div');
        box.id = 'alertToasts';
        box.className = 'alert-toasts';
        box.setAttribute('aria-live', 'polite');
        document.body.appendChild(box);
      }
      const t = document.createElement('a');
      const sos = a.type === 'sos';
      t.className = 'alert-toast' + (sos ? ' sos' : '');
      t.href = a.url;
      t.innerHTML = `<strong>${sos ? T.sosUrgent : T.newAlert}</strong>${esc(a.message)}`;
      box.appendChild(t);
      // An SOS stays until clicked; ordinary alerts fade after 8 seconds.
      if (!sos) setTimeout(() => { t.classList.add('leaving'); setTimeout(() => t.remove(), 300); }, 8000);
    }

    function render(data) {
      const maxId = data.alerts.reduce((m, a) => Math.max(m, a.id), 0);
      if (newestSeen !== null) {
        data.alerts.filter((a) => a.id > newestSeen && !a.read).reverse().slice(-3).forEach(toast);
      }
      newestSeen = Math.max(newestSeen ?? 0, maxId);

      count.hidden = !data.unread;
      count.textContent = data.unread > 99 ? '99+' : data.unread;
      bellBtn.setAttribute('aria-label', data.unread ? T.alertsUnread.replace(':count', data.unread) : T.alerts);
      list.innerHTML = data.alerts.length
        ? data.alerts.map((a) => `<li><a class="${a.read ? '' : 'unread'} ${a.type === 'sos' ? 'sos' : ''}" href="${esc(a.url)}">${esc(a.message)}<small>${esc(a.ago)}</small></a></li>`).join('')
        : `<li class="bell-empty">${esc(T.noAlerts)}</li>`;
    }

    async function load() {
      try {
        const res = await fetch(@json(route('alerts.index')), { headers: { Accept: 'application/json' } });
        if (res.ok) render(await res.json());
      } catch (e) { /* offline: keep what's shown */ }
    }

    function setBellOpen(open) {
      panel.hidden = !open;
      bellBtn.setAttribute('aria-expanded', open);
      if (open) load();
    }
    bellBtn.addEventListener('click', () => setBellOpen(panel.hidden));
    document.addEventListener('click', (e) => { if (!bellMenu.contains(e.target)) setBellOpen(false); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) { setBellOpen(false); bellBtn.focus(); } });

    document.getElementById('bellReadAll').addEventListener('click', async () => {
      await fetch(@json(route('alerts.read-all')), { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } }).catch(() => {});
      load();
    });

    load();
    setInterval(() => { if (document.visibilityState === 'visible') load(); }, POLL_MS);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') load(); });
    window.addEventListener('focus', load);
  })();

  // --- Drivers' Emergency panel ---
  (function () {
    const open = document.getElementById('sosOpen');
    const panel = document.getElementById('sosPanel');
    if (!open || !panel) return;
    const close = () => { panel.hidden = true; open.focus(); };
    open.addEventListener('click', () => { panel.hidden = false; document.getElementById('sosClose').focus(); });
    document.getElementById('sosClose').addEventListener('click', close);
    panel.addEventListener('click', (e) => { if (e.target === panel) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) close(); });
  })();

  function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    themeToggle.setAttribute('aria-checked', theme === 'dark');
    try { localStorage.setItem('theme', theme); } catch (e) {}
    if (window.deliveryChart) {
      const c = getComputedStyle(document.documentElement);
      Chart.defaults.color = c.getPropertyValue('--muted').trim();
      window.deliveryChart.options.scales.y.grid.color = c.getPropertyValue('--border').trim();
      window.deliveryChart.update();
    }
  }
  themeToggle.addEventListener('click', () => {
    applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
  });

  // --- Profile modal ---
  const modal = document.getElementById('profileModal');
  const avatarInput = document.getElementById('avatarInput');
  const preview = document.getElementById('previewAvatar');

  function openModal() { setOpen(false); modal.hidden = false; document.getElementById('pname').focus(); }
  function closeModal() { modal.hidden = true; }
  document.getElementById('openProfile').addEventListener('click', openModal);
  document.getElementById('closeProfile').addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) closeModal(); });

  avatarInput.addEventListener('change', () => {
    const file = avatarInput.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { alert('Please choose an image under 2 MB.'); avatarInput.value = ''; return; }
    const img = document.createElement('img');
    img.src = URL.createObjectURL(file);
    preview.replaceChildren(img);
  });

  @if ($errors->profile->any())
    openModal(); // reopen with the validation errors after a failed save
  @endif
</script>
@stack('scripts')
<script>
  applyTheme(document.documentElement.dataset.theme || 'light'); // sync switch state + chart colors
</script>
</body>
</html>
