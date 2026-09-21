<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logistics – @yield('title')</title>
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
  [data-theme="dark"] .up { color:#5fd68f; } [data-theme="dark"] .down { color:#ff8a8d; }
  body, .card, aside { transition: background-color .2s, color .2s, border-color .2s; }

  .user-menu { position:relative; }
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
  .link-btn { background:none; border:0; color:#d13438; cursor:pointer; padding:0; font:inherit; font-size:.85rem; margin-top:6px; }
  .form-errors { color:#d13438; font-size:.85rem; margin:0 0 14px; padding-left:18px; }
  .flash { background:#dcf5e6; color:#1a8a4a; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:.9rem; }
  [data-theme="dark"] .flash { background:#143524; color:#5fd68f; }
  .modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:8px; }
  .btn { padding:9px 16px; border-radius:8px; border:1px solid var(--border); background:var(--card); color:var(--text); cursor:pointer; font:inherit; }
  .btn.primary { background:var(--primary); border-color:var(--primary); color:#fff; font-weight:600; }
  * { box-sizing:border-box; }
  body { margin:0; display:flex; min-height:100vh; background:var(--bg); color:var(--text); font-family:system-ui,"Segoe UI",sans-serif; }
  aside { width:220px; background:var(--side); color:#fff; padding:24px 16px; flex-shrink:0; position:sticky; top:0; height:100vh; overflow-y:auto; align-self:flex-start; }
  aside .logo { display:flex; align-items:center; gap:8px; font-weight:700; font-size:1.2rem; margin-bottom:28px; padding:0; color:#fff; cursor:pointer; transition:opacity .15s; }
  aside .logo:hover { opacity:.8; background:none; }
  aside a { display:block; padding:10px 12px; border-radius:8px; color:#c9d3f0; text-decoration:none; margin-bottom:4px; }
  aside a.active, aside a:hover { background:rgba(255,255,255,.12); color:#fff; }
  main { flex:1; padding:24px; min-width:0; }
  header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; gap:12px; }
  header h1 { margin:0; font-size:1.5rem; }
  header form { display:flex; align-items:center; gap:12px; font-size:.9rem; }
  header button { padding:8px 14px; border:1px solid var(--border); background:var(--card); border-radius:8px; cursor:pointer; }
  .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px; margin-bottom:24px; }
  .card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:20px; }
  .stat .label { color:var(--muted); font-size:.85rem; }
  .stat .value { font-size:2rem; font-weight:700; margin:6px 0; }
  .up { color:#1a8a4a; } .down { color:#d13438; }
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
  .b-inactive { background:#eef0f5; color:#6b7690; } [data-theme="dark"] .b-inactive { background:#2a3350; color:#9aa6c4; }
  .b-active { background:#dcf5e6; color:#1a8a4a; } [data-theme="dark"] .b-active { background:#143524; color:#5fd68f; }
  .inline { display:inline-flex; gap:6px; align-items:center; margin:0; }
</style>
</head>
<body>
  <aside>
    <a class="logo" href="{{ route('dashboard') }}" aria-label="Logistics – go to dashboard">🚚 Logistics</a>
    @php $u = auth()->user(); @endphp
    <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
    <a class="{{ request()->routeIs('shipments.*') ? 'active' : '' }}" href="{{ route('shipments.index') }}">Shipments</a>
    @if ($u->isSuperAdmin() || $u->hasRole('manager', 'logistics_coordinator'))
      <a class="{{ request()->routeIs('vehicles.*') ? 'active' : '' }}" href="{{ route('vehicles.index') }}">Fleet</a>
      <a class="{{ request()->routeIs('drivers.*') ? 'active' : '' }}" href="{{ route('drivers.index') }}">Drivers</a>
    @endif
    @if ($u->isSuperAdmin() || $u->hasRole('manager'))
      <a class="{{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.activity') }}">Reports</a>
    @endif
    @if ($u->isSuperAdmin())
      <a class="{{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">Users &amp; Roles</a>
    @endif
  </aside>

  <main>
    <header>
      <h1>@yield('heading')</h1>
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
            <small>{{ auth()->user()->role->label() }}</small>
          </span>
          <svg class="chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <div class="dropdown" id="userDropdown" role="menu" hidden>
          <button type="button" class="item" id="openProfile" role="menuitem">Edit profile &amp; photo</button>
          <button type="button" class="item" id="themeToggle" role="menuitemcheckbox" aria-checked="false">
            <span id="themeLabel">Dark mode</span>
            <span class="switch" aria-hidden="true"></span>
          </button>
          <hr>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="item danger" role="menuitem">Sign out</button>
          </form>
        </div>
      </div>
    </header>

    @foreach (['status' => 'success', 'warning' => 'warning', 'error' => 'error'] as $key => $type)
      @if (session($key))
        <x-alert :type="$type">{{ session($key) }}</x-alert>
      @endif
    @endforeach

    @yield('content')
  </main>

  <div class="modal" id="profileModal" role="dialog" aria-modal="true" aria-labelledby="profileTitle" hidden>
    <form class="modal-box" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <h2 id="profileTitle">Edit profile</h2>

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
          <small>JPG, PNG or WebP, up to 2 MB.</small>
          @if (auth()->user()->avatar_path)
            <label style="margin:6px 0 0;font-weight:400"><input type="checkbox" name="remove_avatar" value="1"> Remove current photo</label>
          @endif
        </div>
      </div>

      <label for="pname">Name</label>
      <input type="text" id="pname" name="name" value="{{ old('name', auth()->user()->name) }}" required>
      <label for="pemail">Email</label>
      <input type="email" id="pemail" name="email" value="{{ old('email', auth()->user()->email) }}" required>

      <div class="modal-actions">
        <button type="button" class="btn" id="closeProfile">Cancel</button>
        <button type="submit" class="btn primary">Save changes</button>
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
