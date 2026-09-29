@extends('layouts.app')
@section('title', 'New shipment')
@section('heading', 'New shipment')

@push('head')
<style>
  .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px; }
  .form-grid .full { grid-column:1 / -1; }
  .field label { display:block; font-size:.82rem; font-weight:600; margin-bottom:5px; }
  .field input, .field select, .field textarea { width:100%; padding:9px 10px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.92rem; }
  .field input:focus, .field select:focus, .field textarea:focus { outline:2px solid var(--primary); outline-offset:1px; }
  .section-title { margin:22px 0 12px; font-size:1rem; }
  /* One item = a 2-row grid. Row 1: product | quantity | remove (controls share one height and baseline).
     Row 2: the "available" hint, tucked under the product select only. */
  .line { display:grid; grid-template-columns:minmax(0,1fr) 110px 130px 40px; column-gap:10px; row-gap:4px; align-items:end; margin-bottom:14px; }
  .line .field { min-width:0; }
  .line .field select, .line .field input { height:40px; }
  .line .remove { height:40px; width:40px; padding:0; display:grid; place-items:center; }
  .line .avail { grid-column:1; min-height:1em; font-size:.75rem; color:var(--muted); }
  .errors { color:#d13438; font-size:.85rem; margin:0 0 14px; padding-left:18px; }
  [data-theme="dark"] .errors { color:#ff6b6f; }
  .actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
  a.btn { text-decoration:none; display:inline-block; }
  @media (max-width:560px) { .line { grid-template-columns:minmax(0,1fr) 84px 110px 40px; } }
  #addLine { margin-top:2px; }

  /* Location search: one smart field that resolves to City/Province/Postal behind the
     scenes, with a manual fallback for anything not in the dataset. Nothing here ever
     restricts what can be typed — the dropdown is only ever a shortcut. */
  .loc-link-btn { background:none; border:0; padding:0; color:var(--primary); font:inherit; font-size:.82rem; cursor:pointer; text-decoration:none; }
  .loc-link-btn:hover { text-decoration:underline; }

  .loc-label-row { display:flex; justify-content:space-between; align-items:baseline; gap:12px; margin-bottom:6px; }
  .loc-label-row label { margin:0; }
  .loc-search-box { position:relative; display:flex; align-items:center; border:1px solid var(--border); border-radius:10px; background:var(--card); transition:border-color .15s, box-shadow .15s; }
  .loc-search-box:hover { border-color:color-mix(in srgb, var(--primary) 40%, var(--border)); }
  .loc-search-box:focus-within { border-color:var(--primary); box-shadow:0 0 0 3px rgba(36,84,230,.15); }
  .loc-block.loc-invalid .loc-search-box { border-color:#d13438; box-shadow:0 0 0 3px rgba(209,52,56,.15); }
  .loc-pin { flex:0 0 auto; width:18px; height:18px; margin-left:12px; color:var(--muted); }
  .loc-search-box:focus-within .loc-pin { color:var(--primary); }
  .loc-search-box input { border:0; background:transparent; padding:11px 10px; flex:1; min-width:0; font:inherit; font-size:.95rem; color:var(--text); }
  .loc-search-box input:focus { outline:none; }
  .loc-clear { flex:0 0 auto; width:26px; height:26px; margin-right:8px; border:0; border-radius:50%; background:transparent; color:var(--muted); font-size:1.2rem; line-height:1; cursor:pointer; }
  .loc-clear:hover { background:var(--hover, rgba(120,120,120,.14)); color:var(--text); }
  .loc-hint { display:block; margin-top:6px; color:var(--muted); font-size:.78rem; }
  .loc-block.loc-invalid .loc-hint { color:#d13438; }
  .loc-kbd-hint { padding:6px 10px 2px; color:var(--muted); font-size:.72rem; border-top:1px solid var(--border); margin-top:4px; cursor:default; }
  .loc-spinner { flex:0 0 auto; width:14px; height:14px; margin-right:11px; border:2px solid var(--border); border-top-color:var(--primary); border-radius:50%; animation:loc-spin .6s linear infinite; }
  @keyframes loc-spin { to { transform:rotate(360deg); } }

  .combo-list { position:absolute; z-index:20; top:calc(100% + 6px); left:0; right:0; margin:0; padding:6px; list-style:none;
                background:var(--card); border:1px solid var(--border); border-radius:10px; box-shadow:0 12px 28px rgba(0,0,0,.18);
                max-height:260px; overflow-y:auto; animation:loc-pop .12s ease-out; }
  @keyframes loc-pop { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:translateY(0); } }
  .combo-list[hidden] { display:none; }
  .combo-list li { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:7px; cursor:pointer; font-size:.88rem; }
  .combo-list li .loc-row-pin { flex:0 0 auto; width:15px; height:15px; color:var(--muted); }
  .combo-list li strong { font-weight:600; }
  .combo-list li strong mark { background:rgba(36,84,230,.18); color:inherit; border-radius:2px; padding:0 1px; }
  [data-theme="dark"] .combo-list li strong mark { background:rgba(79,123,255,.28); }
  .combo-list li small { display:block; color:var(--muted); font-size:.75rem; }
  .combo-list li:hover, .combo-list li[aria-selected="true"] { background:var(--hover, rgba(120,120,120,.14)); }
  .combo-list li.loc-empty { cursor:default; color:var(--muted); font-size:.82rem; }
  .combo-list li.loc-empty:hover, .combo-list li.loc-kbd-hint:hover { background:none; }

  .loc-resolved-card { display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid color-mix(in srgb, var(--primary) 35%, var(--border)); border-radius:10px;
                       background:color-mix(in srgb, var(--primary) 7%, var(--card)); animation:loc-pop .15s ease-out; }
  .loc-resolved-card .loc-pin { margin-left:0; width:20px; height:20px; color:var(--primary); }
  .loc-resolved-card .loc-resolved-body { flex:1; min-width:0; }
  .loc-resolved-card .loc-resolved-city { display:block; font-weight:600; font-size:.98rem; }
  .loc-resolved-card .loc-resolved-sub { display:block; color:var(--muted); font-size:.82rem; margin-top:2px; }
  .loc-resolved-actions { display:flex; gap:6px; flex-shrink:0; }

  .loc-manual { padding:14px; border:1px dashed var(--border); border-radius:10px; }
  .loc-manual-grid { grid-template-columns:2fr 2fr 1fr; margin:0; }
  .loc-manual-grid .field { margin-bottom:0; }
  @media (max-width:640px) { .loc-manual-grid { grid-template-columns:1fr; } .loc-resolved-card { flex-wrap:wrap; } }
  [hidden] { display:none !important; }
</style>
@endpush

@section('content')
  <form class="card" method="POST" action="{{ route('shipments.store') }}" id="shipmentForm">
    @csrf

    @if ($errors->any())
      <ul class="errors">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif

    <h2 class="section-title" style="margin-top:0">Origin</h2>
    <div class="form-grid">
      <div class="field"><label for="origin_name">Name / warehouse</label>
        <div style="position:relative">
          <input id="origin_name" name="origin_name" value="{{ old('origin_name') }}" required autocomplete="off"
                 placeholder="Pick from Inventory or type a name" role="combobox" aria-controls="origin_name_list" aria-expanded="false">
          <ul class="combo-list" id="origin_name_list" role="listbox" hidden></ul>
        </div>
        <input type="hidden" id="origin_latitude" name="origin_latitude" value="{{ old('origin_latitude') }}">
        <input type="hidden" id="origin_longitude" name="origin_longitude" value="{{ old('origin_longitude') }}">
      </div>
      <div class="field"><label for="origin_address">Address</label><input id="origin_address" name="origin_address" value="{{ old('origin_address') }}" required></div>
      @include('shipments._location-block', ['prefix' => 'origin'])
    </div>

    <h2 class="section-title">Destination</h2>
    <div class="form-grid">
      <div class="field"><label for="destination_name">Recipient / store</label>
        <div style="position:relative">
          <input id="destination_name" name="destination_name" value="{{ old('destination_name') }}" required autocomplete="off"
                 placeholder="Pick from Inventory or type a name" role="combobox" aria-controls="destination_name_list" aria-expanded="false">
          <ul class="combo-list" id="destination_name_list" role="listbox" hidden></ul>
        </div>
        <input type="hidden" id="destination_latitude" name="destination_latitude" value="{{ old('destination_latitude') }}">
        <input type="hidden" id="destination_longitude" name="destination_longitude" value="{{ old('destination_longitude') }}">
      </div>
      <div class="field"><label for="destination_address">Address</label><input id="destination_address" name="destination_address" value="{{ old('destination_address') }}" required></div>
      @include('shipments._location-block', ['prefix' => 'destination'])
    </div>

    <h2 class="section-title">Schedule &amp; assignment</h2>
    <div class="form-grid">
      <div class="field"><label for="scheduled_delivery_at">Scheduled delivery</label>
        <input type="datetime-local" id="scheduled_delivery_at" name="scheduled_delivery_at" value="{{ old('scheduled_delivery_at') }}" required
               min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}"></div>
      @if ($drivers->count())
        <div class="field"><label for="driver_id">Driver</label>
          <select id="driver_id" name="driver_id"><option value="">Unassigned</option>
            @foreach ($drivers as $d)<option value="{{ $d->id }}" @selected(old('driver_id') == $d->id)>{{ $d->name }}</option>@endforeach
          </select></div>
      @endif
      @if ($vehicles->count())
        <div class="field"><label for="vehicle_id">Vehicle</label>
          <select id="vehicle_id" name="vehicle_id"><option value="">Unassigned</option>
            @foreach ($vehicles as $v)<option value="{{ $v->id }}" @selected(old('vehicle_id') == $v->id)>{{ $v->plate_number }}</option>@endforeach
          </select></div>
      @endif
      <div class="field full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2">{{ old('notes') }}</textarea></div>
    </div>

    <h2 class="section-title">Items from inventory</h2>
    @if ($inventoryDown)
      <p class="errors">The inventory system can't be reached right now, so items can't be added. Try again shortly.</p>
    @elseif (! count($stockOptions))
      <p class="errors">No products with stock were found in inventory.</p>
    @else
      <div id="lines"></div>
      <button type="button" class="btn sm" id="addLine">+ Add item</button>
      <p style="color:var(--muted);font-size:.8rem;margin:10px 0 0">Stock is checked when you save. Shipments don't reduce inventory quantities.
        Unit weight is optional and only needed if you plan to use automatic multi-vehicle dispatch for this shipment.</p>
    @endif

    <div class="actions">
      <a class="btn" href="{{ route('shipments.index') }}">Cancel</a>
      <button type="submit" class="btn primary" @disabled($inventoryDown || ! count($stockOptions))>Create shipment</button>
    </div>
  </form>
@endsection

@push('scripts')
<script>
  // Origin / destination name suggestions from Inventory (locations and suppliers).
  // Picking one fills the name and address; typing anything else is still allowed.
  (function () {
    const places = @json($placeOptions);
    if (!places.length) return;

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    ['origin', 'destination'].forEach((prefix) => {
      const input = document.getElementById(prefix + '_name');
      const list = document.getElementById(prefix + '_name_list');
      const address = document.getElementById(prefix + '_address');
      const lat = document.getElementById(prefix + '_latitude');
      const lng = document.getElementById(prefix + '_longitude');
      // The pin only belongs to the picked Inventory place; editing the name or
      // address by hand drops it and the server looks the address up instead.
      const clearPin = () => { lat.value = ''; lng.value = ''; };
      address?.addEventListener('input', clearPin);
      let matches = [];
      let active = -1;

      function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        active = -1;
      }

      function render() {
        const q = input.value.trim().toLowerCase();
        matches = places.filter((p) =>
          !q || p.name.toLowerCase().includes(q) || p.address.toLowerCase().includes(q)
        ).slice(0, 30);

        if (!matches.length) { close(); return; }

        list.innerHTML = matches.map((p, i) => {
          let name = esc(p.name);
          if (q) {
            const at = p.name.toLowerCase().indexOf(q);
            if (at >= 0) {
              name = esc(p.name.slice(0, at)) + '<mark>' + esc(p.name.slice(at, at + q.length)) + '</mark>' + esc(p.name.slice(at + q.length));
            }
          }
          const sub = [p.type, p.sub, p.address || p.city].filter(Boolean).map(esc).join(' · ');
          return '<li role="option" data-i="' + i + '" aria-selected="' + (i === active) + '"><div><strong>' + name + '</strong><small>' + sub + '</small></div></li>';
        }).join('');

        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
      }

      function choose(i) {
        const p = matches[i];
        if (!p) return;
        input.value = p.name;
        if (address && p.address) address.value = p.address;
        lat.value = p.lat ?? '';
        lng.value = p.lng ?? '';
        if (p.city) {
          document.getElementById(prefix + '_loc_block')?.dispatchEvent(new CustomEvent('loc:set', {
            detail: { city: p.city, province: p.province || '', postal_code: p.postal_code || '' },
          }));
        }
        close();
      }

      input.addEventListener('focus', render);
      input.addEventListener('input', () => { clearPin(); active = -1; render(); });
      input.addEventListener('keydown', (e) => {
        if (list.hidden) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, matches.length - 1); render(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); render(); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); choose(active); }
        else if (e.key === 'Escape') { close(); }
      });
      // mousedown fires before the input's blur, so the click isn't lost.
      list.addEventListener('mousedown', (e) => {
        const li = e.target.closest('li');
        if (li) { e.preventDefault(); choose(Number(li.dataset.i)); }
      });
      input.addEventListener('blur', close);
    });
  })();

  const stock = @json($stockOptions);
  const previous = @json(old('items', []));
  const linesEl = document.getElementById('lines');

  function addLine(key = '', qty = 1, weight = '') {
    if (!linesEl) return;
    const i = linesEl.children.length + Date.now(); // unique index per row
    const row = document.createElement('div');
    row.className = 'line';
    const options = stock.map(s => `<option value="${s.key}" data-avail="${s.available}" ${s.key === key ? 'selected' : ''}>${s.label.replace(/</g, '&lt;')}</option>`).join('');
    row.innerHTML = `
      <div class="field"><label>Product · source location</label>
        <select name="items[${i}][key]" required><option value="">Select…</option>${options}</select></div>
      <div class="field"><label>Quantity</label><input type="number" min="1" name="items[${i}][quantity]" value="${qty}" required></div>
      <div class="field"><label>Unit weight (kg)</label><input type="number" min="0" step="0.01" name="items[${i}][unit_weight_kg]" value="${weight}" placeholder="optional"></div>
      <button type="button" class="btn remove" aria-label="Remove item">✕</button>
      <div class="avail"></div>`;
    const select = row.querySelector('select'), input = row.querySelector('input'), avail = row.querySelector('.avail');
    const refresh = () => {
      const max = select.selectedOptions[0]?.dataset.avail;
      avail.textContent = max ? `${max} available` : '';
      if (max) input.max = max; else input.removeAttribute('max');
    };
    select.addEventListener('change', refresh);
    row.querySelector('button').addEventListener('click', () => { if (linesEl.children.length > 1) row.remove(); });
    linesEl.appendChild(row);
    refresh();
  }

  document.getElementById('addLine')?.addEventListener('click', () => addLine());
  const restored = Object.values(previous);
  if (restored.length) restored.forEach(l => addLine(l.key, l.quantity, l.unit_weight_kg ?? '')); else addLine();

  /**
   * "Smart location" search: one field that resolves City/Province/Postal from the
   * Philippine locations dataset, with a manual mode always one click away. The real
   * #{prefix}_city / _province / _postal_code inputs are the only things ever submitted —
   * the search box itself has no name. Three views (search, picked-city card, manual
   * fields), one visible at a time. Two things keep the free-text fallback honest:
   *   1. Every keystroke in the search box is mirrored into the real City field as plain
   *      text, so a custom location typed but never "selected" still saves correctly.
   *   2. Switching views never clears a value another view set.
   */
  function initLocationBlock(prefix) {
    const block = document.getElementById(`${prefix}_loc_block`);
    if (!block) return;

    const $ = (id) => document.getElementById(`${prefix}_${id}`);
    const views = { search: $('loc_search'), resolved: $('loc_resolved'), manual: $('loc_manual') };
    const query = $('loc_query');
    const spinner = $('loc_spinner');
    const clearBtn = $('loc_clear');
    const list = $('loc_list');
    const hint = $('loc_hint');
    const hintText = hint.textContent;
    const manualBtn = $('loc_manual_btn');
    const searchBtn = $('loc_search_btn');
    const city = $('city');
    const province = $('province');
    const postal = $('postal_code');

    let requestId = 0;
    let activeIndex = -1;
    let results = [];

    function show(view, focus) {
      Object.entries(views).forEach(([name, el]) => { el.hidden = name !== view; });
      manualBtn.hidden = view === 'manual';
      searchBtn.hidden = view !== 'manual';
      closeList();
      setInvalid(false);
      if (view === 'resolved') {
        $('loc_resolved_city').textContent = city.value;
        $('loc_resolved_sub').textContent = [province.value, postal.value].filter(Boolean).join(' · ');
      }
      focus?.focus();
    }

    function showSearch() {
      query.value = city.value; // pick up wherever the other views left off
      clearBtn.hidden = !query.value;
      show('search', query);
      query.select();
    }

    function setInvalid(on) {
      block.classList.toggle('loc-invalid', on);
      hint.textContent = on ? 'Please choose or enter a location.' : hintText;
    }

    function closeList() {
      list.hidden = true;
      query.setAttribute('aria-expanded', 'false');
      query.removeAttribute('aria-activedescendant');
      activeIndex = -1;
    }

    const pinIcon = '<svg class="loc-row-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>';

    function escapeHtml(s) {
      return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function highlight(text, q) {
      const i = text.toLowerCase().indexOf(q.toLowerCase());
      if (i === -1 || !q) return escapeHtml(text);
      return escapeHtml(text.slice(0, i)) + '<mark>' + escapeHtml(text.slice(i, i + q.length)) + '</mark>' + escapeHtml(text.slice(i + q.length));
    }

    function renderList() {
      const q = query.value.trim();
      if (results.length === 0) {
        list.innerHTML = q.length >= 2
          ? `<li class="loc-empty">No matches for “${escapeHtml(q)}”. <button type="button" class="loc-link-btn" data-manual>Enter it manually</button></li>` : '';
        list.hidden = list.innerHTML === '';
        list.querySelector('[data-manual]')?.addEventListener('mousedown', (e) => { e.preventDefault(); show('manual', province); });
      } else {
        list.innerHTML = results.map((r, i) => `
          <li role="option" id="${list.id}-opt-${i}" aria-selected="${i === activeIndex}">
            ${pinIcon}
            <span><strong>${highlight(r.city, q)}</strong><small>${escapeHtml(r.province)} · ${escapeHtml(r.postal_code)}</small></span>
          </li>`).join('') + '<li class="loc-kbd-hint" aria-hidden="true">↑ ↓ to move · Enter to select · Esc to close</li>';
        list.hidden = false;
        [...list.querySelectorAll('[role=option]')].forEach((li, i) => li.addEventListener('mousedown', (e) => {
          e.preventDefault(); // keep focus in the input instead of blurring to the <li>
          selectResult(results[i]);
        }));
        if (activeIndex >= 0) {
          query.setAttribute('aria-activedescendant', `${list.id}-opt-${activeIndex}`);
          document.getElementById(`${list.id}-opt-${activeIndex}`)?.scrollIntoView({ block: 'nearest' });
        }
      }
      query.setAttribute('aria-expanded', String(!list.hidden));
    }

    function selectResult(r) {
      city.value = r.city;
      province.value = r.province;
      postal.value = r.postal_code;
      show('resolved', $('loc_change_btn'));
    }

    // Lets other scripts (the Inventory place picker) fill this block.
    block.addEventListener('loc:set', (e) => selectResult(e.detail));

    async function search() {
      const q = query.value.trim();
      if (q.length < 1) { results = []; spinner.hidden = true; closeList(); return; }

      const myRequest = ++requestId;
      spinner.hidden = false;
      clearBtn.hidden = true;
      try {
        const res = await fetch(`{{ route('locations.search') }}?${new URLSearchParams({ q, field: 'city' })}`, { headers: { Accept: 'application/json' } });
        if (!res.ok || myRequest !== requestId) return; // a newer keystroke has already superseded this request
        results = await res.json();
      } catch {
        results = []; // offline/network hiccup: fail silently, typing freely still works
      }
      if (myRequest === requestId) { spinner.hidden = true; clearBtn.hidden = !query.value; activeIndex = -1; renderList(); }
    }

    let debounceTimer;
    query.addEventListener('input', () => {
      // Mirror raw typing into the real field even without a pick; a new city means the
      // previous pick's province/postal no longer apply.
      city.value = query.value;
      province.value = '';
      postal.value = '';
      clearBtn.hidden = !query.value;
      setInvalid(false);
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(search, 200);
    });

    query.addEventListener('focus', () => { if (results.length && query.value.trim()) renderList(); });

    query.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { closeList(); return; }
      if (list.hidden || results.length === 0) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); activeIndex = Math.min(activeIndex + 1, results.length - 1); renderList(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); activeIndex = Math.max(activeIndex - 1, 0); renderList(); }
      else if (e.key === 'Enter') { e.preventDefault(); selectResult(results[Math.max(activeIndex, 0)]); }
    });

    query.addEventListener('blur', () => setTimeout(closeList, 150)); // delay so a click on an option still registers

    clearBtn.addEventListener('click', () => {
      query.value = city.value = province.value = postal.value = '';
      results = [];
      clearBtn.hidden = true;
      closeList();
      query.focus();
    });

    manualBtn.addEventListener('click', () => show('manual', city));
    searchBtn.addEventListener('click', showSearch);
    $('loc_change_btn').addEventListener('click', showSearch);
    $('loc_edit_btn').addEventListener('click', () => show('manual', city));

    // City is required but lives in a view that may be hidden, where the browser can't
    // point at it. Show the problem on whichever view is visible instead.
    city.addEventListener('invalid', () => {
      if (!views.manual.hidden) return;
      showSearch();
      setInvalid(true);
    });
  }

  initLocationBlock('origin');
  initLocationBlock('destination');

  // Keep "no past dates/times" accurate to the browser's own clock, not just the moment the
  // page was rendered — a page left open a while shouldn't quietly let a stale minute through.
  // The server's own `after:now` validation is still the real enforcement either way.
  (function refreshScheduleMin() {
    const input = document.getElementById('scheduled_delivery_at');
    function apply() {
      const d = new Date(Date.now() + 60000); // +1 minute, matching the backend's strict "after now"
      const pad = (n) => String(n).padStart(2, '0');
      input.min = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }
    apply();
    input.addEventListener('focus', apply);
    setInterval(apply, 60000);
  })();
</script>
@endpush
