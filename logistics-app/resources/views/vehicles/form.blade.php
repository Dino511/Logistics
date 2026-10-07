@extends('layouts.app')
@php $editing = $vehicle->exists; @endphp
@section('title', $editing ? 'Edit vehicle' : 'Add vehicle')
@section('heading', $editing ? 'Edit vehicle' : 'Add vehicle')

@push('head')
<style>
  .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px; max-width:720px; }
  .field label { display:block; font-size:.82rem; font-weight:600; margin-bottom:5px; }
  .field input, .field select { width:100%; padding:9px 10px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.92rem; }
  .field input:focus, .field select:focus { outline:2px solid var(--primary); outline-offset:1px; }
  .errors { color:#d13438; font-size:.85rem; margin:0 0 14px; padding-left:18px; } [data-theme="dark"] .errors { color:#ff6b6f; }
  .actions { display:flex; gap:10px; margin-top:20px; }
  a.btn { text-decoration:none; display:inline-block; }
  .checkbox-field { display:flex; align-items:center; gap:8px; font-size:.9rem; font-weight:600; margin:20px 0 4px; }
  .checkbox-field input { width:auto; }
  .lease-section { border-top:1px solid var(--border); margin-top:16px; padding-top:16px; }
  .lease-section[hidden] { display:none; }
  .lease-section h2 { margin:0 0 14px; font-size:1rem; }
  .lease-section small.hint { display:block; color:var(--muted); font-size:.75rem; margin-top:4px; }
  .current-doc { display:flex; align-items:center; gap:10px; font-size:.85rem; margin-top:6px; }
  .current-doc a { color:var(--primary); }
  /* Rate frequency + amount: one logical field, two controls side by side. */
  .rate-field { display:flex; gap:8px; }
  .rate-field select { flex:0 0 130px; }
  .rate-field input { flex:1; min-width:0; }
</style>
@endpush

@section('content')
  <form class="card" method="POST" action="{{ $editing ? route('vehicles.update', $vehicle) : route('vehicles.store') }}" enctype="multipart/form-data">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
      <ul class="errors">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif

    <div class="form-grid">
      <div class="field"><label for="type">Type</label>
        <select id="type" name="type" required>
          <option value="">Select…</option>
          @foreach (\App\Models\Vehicle::TYPES as $t)
            <option value="{{ $t }}" data-payload="{{ \App\Models\Vehicle::TYPICAL_PAYLOAD_KG[$t] ?? '' }}" @selected(old('type', $vehicle->type) === $t)>{{ $t }}</option>
          @endforeach
        </select></div>
      <div class="field"><label for="plate_number">Plate number</label>
        <input id="plate_number" name="plate_number" value="{{ old('plate_number', $vehicle->plate_number) }}" required maxlength="8"
               placeholder="ABC 1234" style="text-transform:uppercase" autocomplete="off"
               pattern="^[A-Za-z]{3}\s?\d{3,4}$" title="3 letters followed by 3 or 4 numbers, e.g. ABC 123 or ABC 1234">
        <small class="hint">3 letters + 3 or 4 numbers, e.g. ABC 123 or ABC 1234. A space is optional.</small></div>
      <div class="field"><label for="capacity_kg">Capacity (kg)</label>
        <input type="number" step="0.01" min="0" id="capacity_kg" name="capacity_kg" value="{{ old('capacity_kg', $vehicle->capacity_kg) }}">
        <small id="payloadHint" style="display:block;color:var(--muted);font-size:.75rem;margin-top:4px"></small></div>
      <div class="field"><label for="status">Status</label>
        <select id="status" name="status" required>
          @foreach (\App\Models\Vehicle::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $vehicle->status) === $s)>{{ \App\Models\Vehicle::statusLabel($s) }}</option>@endforeach
        </select></div>
    </div>

    <label class="checkbox-field">
      <input type="checkbox" id="is_rented" name="is_rented" value="1" @checked(old('is_rented', $vehicle->is_rented))>
      This is a rented / third-party vehicle
    </label>

    <div class="lease-section" id="leaseSection">
      <h2>Lease agreement <span style="font-weight:400;color:var(--muted);font-size:.8rem">(optional unless the box above is checked)</span></h2>
      <div class="form-grid">
        <div class="field"><label for="lessor_name">Lessor name</label>
          <input id="lessor_name" name="lessor_name" value="{{ old('lessor_name', $lease->lessor_name) }}" maxlength="150"></div>
        <div class="field"><label for="lease_status">Contract status</label>
          <select id="lease_status" name="lease_status">
            @foreach (\App\Models\VehicleLease::STATUSES as $s)
              <option value="{{ $s }}" @selected(old('lease_status', $lease->status ?? 'active') === $s)>{{ \App\Models\VehicleLease::statusLabel($s) }}</option>
            @endforeach
          </select></div>
        @php
          // Only floor the picker at today when that wouldn't invalidate a start date
          // already on file — an existing lease that legitimately started in the past
          // must still open and save without the browser's own date-range check blocking it.
          $startAlreadyPast = $lease->start_date && $lease->start_date->isPast() && ! $lease->start_date->isToday();
        @endphp
        <div class="field"><label for="start_date">Lease start date</label>
          <input type="date" id="start_date" name="start_date" value="{{ old('start_date', optional($lease->start_date)->format('Y-m-d')) }}"
                 @unless($startAlreadyPast) min="{{ now()->format('Y-m-d') }}" @endunless>
          <small class="hint">A new or changed start date can't be in the past — this doesn't affect a lease already on file.</small></div>
        @php
          // The end-date floor is whichever is later: today, or the day after the current
          // start date value (server-rendered fallback; JS takes over once the user actually
          // picks a start date). Omitted entirely — same as start_date above — when the
          // existing end date already predates that floor, so an already-on-file lease
          // (even a same-day one, allowed under the old rule) can still be opened and saved.
          $startValue = old('start_date', optional($lease->start_date)->format('Y-m-d'));
          $dayAfterStart = $startValue ? \Illuminate\Support\Carbon::parse($startValue)->addDay()->format('Y-m-d') : now()->format('Y-m-d');
          $endFloor = max(now()->format('Y-m-d'), $dayAfterStart);
          $endValue = old('end_date', optional($lease->end_date)->format('Y-m-d'));
          $endAlreadyInvalid = $endValue && $endValue < $endFloor;
        @endphp
        <div class="field"><label for="end_date">Lease end date</label>
          <input type="date" id="end_date" name="end_date" value="{{ $endValue }}" @unless($endAlreadyInvalid) min="{{ $endFloor }}" @endunless>
          <small class="hint">Leave blank for an open-ended contract. Must be after the start date.</small></div>
        <div class="field">
          <label for="rate_amount">Rental rate</label>
          <div class="rate-field">
            <select id="rate_type" name="rate_type" aria-label="Rate frequency">
              <option value="">Frequency…</option>
              @foreach (\App\Models\VehicleLease::RATE_TYPES as $rt)
                <option value="{{ $rt }}" @selected(old('rate_type', $lease->rate_type) === $rt)>{{ \App\Models\VehicleLease::rateTypeLabel($rt) }}</option>
              @endforeach
            </select>
            <input type="number" step="0.01" min="0" id="rate_amount" name="rate_amount" placeholder="Amount (₱)" value="{{ old('rate_amount', $lease->rate_amount) }}">
          </div>
          <small class="hint">Choose how often the rate is charged, then enter the amount.</small>
        </div>
        <div class="field"><label for="document">Contract document (PDF)</label>
          <input type="file" id="document" name="document" accept="application/pdf">
          @if ($lease->document_path)
            <div class="current-doc">
              <a href="{{ $lease->documentUrl() }}" target="_blank" rel="noopener">View current contract</a>
              <label style="font-weight:400"><input type="checkbox" name="remove_document" value="1"> Remove</label>
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="actions">
      @if ($editing)
        <button type="submit" class="btn primary">Save changes</button>
      @else
        <button type="submit" name="action" value="save_and_add" class="btn">Save &amp; Add Another</button>
        <button type="submit" name="action" value="save" class="btn primary">Add vehicle</button>
      @endif
      <a class="btn" href="{{ route('vehicles.index') }}">Cancel</a>
    </div>
  </form>
@endsection

@push('scripts')
<script>
  // Suggest a typical payload for the chosen model. Switching models should refresh the
  // suggestion, but a value the user typed themselves (or a real saved capacity, when
  // editing) must never be silently overwritten — so we only track "auto" as belonging to
  // whatever we last filled in ourselves, and drop that flag the moment the user edits it.
  const typeSelect = document.getElementById('type');
  const capacityInput = document.getElementById('capacity_kg');
  const hint = document.getElementById('payloadHint');
  let autoFilled = false;
  let lastType = typeSelect.value; // only an actual change of selection counts as "picked a different model"

  function suggestPayload() {
    const modelChanged = typeSelect.value !== lastType;
    lastType = typeSelect.value;

    const payload = typeSelect.selectedOptions[0]?.dataset.payload;
    if (!payload) { hint.textContent = ''; return; }
    hint.textContent = `Typical for this model: ~${Number(payload).toLocaleString()} kg`;

    // Fill when the field is empty, when we own the current value, or when the user just
    // picked a genuinely different model (the whole point of this fix) — never on an
    // untouched saved value, and never just because the change event fired again.
    if (!capacityInput.value || autoFilled || modelChanged) {
      capacityInput.value = payload;
      autoFilled = true;
    }
  }

  // Any real keystroke/paste means the user has taken over — stop touching this field
  // until they deliberately change the model again.
  capacityInput.addEventListener('input', () => { autoFilled = false; });
  typeSelect.addEventListener('change', suggestPayload);
  suggestPayload();

  // Show the lease section only when the vehicle is marked as rented.
  const rentedCheckbox = document.getElementById('is_rented');
  const leaseSection = document.getElementById('leaseSection');
  function toggleLeaseSection() { leaseSection.hidden = !rentedCheckbox.checked; }
  rentedCheckbox.addEventListener('change', toggleLeaseSection);
  toggleLeaseSection();

  // Keep the start-date floor accurate to the browser's live clock rather than just the
  // moment the page rendered. Only touches it when the server actually set a `min` — a lease
  // that already legitimately started in the past deliberately has no `min`, so it stays editable.
  const startDateInput = document.getElementById('start_date');
  if (startDateInput.min) {
    const refreshMin = () => {
      const d = new Date();
      const pad = (n) => String(n).padStart(2, '0');
      startDateInput.min = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    };
    startDateInput.addEventListener('focus', refreshMin);
    setInterval(refreshMin, 60000);
  }

  // Dynamic minDate coupling: end date's floor follows whatever start date is picked, so
  // an end date before or on the same day as the start date is never selectable. Any
  // interaction with start_date from here on is a live, deliberate edit — unlike the
  // server-rendered initial `min` above, this is always safe to enforce going forward.
  const endDateInput = document.getElementById('end_date');
  function todayStr() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }
  function dayAfter(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    d.setDate(d.getDate() + 1);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }
  function syncEndDateFloor() {
    const floor = startDateInput.value ? dayAfter(startDateInput.value) : todayStr();
    endDateInput.min = floor > todayStr() ? floor : todayStr();
    // The end date no longer satisfies the new floor — clear it rather than silently
    // resubmit a now-invalid value the user didn't actually choose.
    if (endDateInput.value && endDateInput.value < endDateInput.min) endDateInput.value = '';
  }
  // Deliberately not called once on page load: doing so would immediately clear an
  // already-on-file end date that predates the newly computed floor (e.g. an old same-day
  // lease, valid when saved) even though the user hasn't touched anything yet. It only
  // ever runs from here on, in response to the user actually changing the start date.
  startDateInput.addEventListener('input', syncEndDateFloor);
</script>
@endpush
