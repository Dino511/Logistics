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
  .line { display:grid; grid-template-columns:minmax(0,1fr) 110px 40px; column-gap:10px; row-gap:4px; align-items:end; margin-bottom:14px; }
  .line .field { min-width:0; }
  .line .field select, .line .field input { height:40px; }
  .line .remove { height:40px; width:40px; padding:0; display:grid; place-items:center; }
  .line .avail { grid-column:1; min-height:1em; font-size:.75rem; color:var(--muted); }
  .errors { color:#d13438; font-size:.85rem; margin:0 0 14px; padding-left:18px; }
  [data-theme="dark"] .errors { color:#ff6b6f; }
  .actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
  a.btn { text-decoration:none; display:inline-block; }
  @media (max-width:560px) { .line { grid-template-columns:minmax(0,1fr) 84px 40px; } }
  #addLine { margin-top:2px; }
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
      <div class="field"><label for="origin_name">Name / warehouse</label><input id="origin_name" name="origin_name" value="{{ old('origin_name') }}" required></div>
      <div class="field"><label for="origin_address">Address</label><input id="origin_address" name="origin_address" value="{{ old('origin_address') }}" required></div>
      <div class="field"><label for="origin_city">City</label><input id="origin_city" name="origin_city" value="{{ old('origin_city') }}" required></div>
      <div class="field"><label for="origin_province">Province</label><input id="origin_province" name="origin_province" value="{{ old('origin_province') }}"></div>
      <div class="field"><label for="origin_postal_code">Postal code</label><input id="origin_postal_code" name="origin_postal_code" value="{{ old('origin_postal_code') }}"></div>
    </div>

    <h2 class="section-title">Destination</h2>
    <div class="form-grid">
      <div class="field"><label for="destination_name">Recipient / store</label><input id="destination_name" name="destination_name" value="{{ old('destination_name') }}" required></div>
      <div class="field"><label for="destination_address">Address</label><input id="destination_address" name="destination_address" value="{{ old('destination_address') }}" required></div>
      <div class="field"><label for="destination_city">City</label><input id="destination_city" name="destination_city" value="{{ old('destination_city') }}" required></div>
      <div class="field"><label for="destination_province">Province</label><input id="destination_province" name="destination_province" value="{{ old('destination_province') }}"></div>
      <div class="field"><label for="destination_postal_code">Postal code</label><input id="destination_postal_code" name="destination_postal_code" value="{{ old('destination_postal_code') }}"></div>
    </div>

    <h2 class="section-title">Schedule &amp; assignment</h2>
    <div class="form-grid">
      <div class="field"><label for="scheduled_delivery_at">Scheduled delivery</label><input type="datetime-local" id="scheduled_delivery_at" name="scheduled_delivery_at" value="{{ old('scheduled_delivery_at') }}" required></div>
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
      <p style="color:var(--muted);font-size:.8rem;margin:10px 0 0">Stock is checked when you save. Shipments don't reduce inventory quantities.</p>
    @endif

    <div class="actions">
      <a class="btn" href="{{ route('shipments.index') }}">Cancel</a>
      <button type="submit" class="btn primary" @disabled($inventoryDown || ! count($stockOptions))>Create shipment</button>
    </div>
  </form>
@endsection

@push('scripts')
<script>
  const stock = @json($stockOptions);
  const previous = @json(old('items', []));
  const linesEl = document.getElementById('lines');

  function addLine(key = '', qty = 1) {
    if (!linesEl) return;
    const i = linesEl.children.length + Date.now(); // unique index per row
    const row = document.createElement('div');
    row.className = 'line';
    const options = stock.map(s => `<option value="${s.key}" data-avail="${s.available}" ${s.key === key ? 'selected' : ''}>${s.label.replace(/</g, '&lt;')}</option>`).join('');
    row.innerHTML = `
      <div class="field"><label>Product · source location</label>
        <select name="items[${i}][key]" required><option value="">Select…</option>${options}</select></div>
      <div class="field"><label>Quantity</label><input type="number" min="1" name="items[${i}][quantity]" value="${qty}" required></div>
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
  if (restored.length) restored.forEach(l => addLine(l.key, l.quantity)); else addLine();
</script>
@endpush
