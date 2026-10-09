@extends('layouts.app')
@php $editing = $driver->exists; @endphp
@section('title', $editing ? 'Edit driver' : 'Add driver')
@section('heading', $editing ? 'Edit driver' : 'Add driver')

@push('head')
<style>
  .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:14px; max-width:720px; }
  .field label { display:block; font-size:.82rem; font-weight:600; margin-bottom:5px; }
  .field input, .field select { width:100%; padding:9px 10px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.92rem; }
  .field input:focus, .field select:focus { outline:2px solid var(--primary); outline-offset:1px; }
  .errors { color:#d13438; font-size:.85rem; margin:0 0 14px; padding-left:18px; } [data-theme="dark"] .errors { color:#ff6b6f; }
  .field small.hint { display:block; color:var(--muted); font-size:.75rem; margin-top:4px; }
  .phone-mask { display:flex; align-items:stretch; border:1px solid var(--border); border-radius:8px; overflow:hidden; }
  .phone-mask .prefix { display:flex; align-items:center; padding:0 10px; background:var(--hover, rgba(120,120,120,.12)); color:var(--muted); font-weight:600; font-size:.92rem; user-select:none; }
  .phone-mask input { border:0; border-radius:0; flex:1; min-width:0; }
  .phone-mask input:focus { outline:none; box-shadow:inset 0 0 0 2px var(--primary); }
  .actions { display:flex; gap:10px; margin-top:20px; }
  a.btn { text-decoration:none; display:inline-block; }
</style>
@endpush

@section('content')
  <form class="card" method="POST" action="{{ $editing ? route('drivers.update', $driver) : route('drivers.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
      <ul class="errors">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif

    <div class="form-grid">
      <div class="field"><label for="name">Full name</label>
        <input id="name" name="name" value="{{ old('name', $driver->name) }}" required maxlength="255"></div>
      <div class="field"><label for="phone_digits">Phone</label>
        <div class="phone-mask">
          <span class="prefix" aria-hidden="true">+63</span>
          <input type="text" id="phone_digits" inputmode="numeric" autocomplete="off" maxlength="10" placeholder="9171234567"
                 aria-label="Mobile number, 10 digits, without +63">
        </div>
        <input type="hidden" id="phone" name="phone" value="{{ old('phone', $driver->phone) }}">
        <small class="hint">Enter the 10 digits after +63, e.g. 9171234567 for +639171234567.</small></div>
      <div class="field"><label for="license_number">License number</label>
        <input id="license_number" name="license_number" value="{{ old('license_number', $driver->license_number) }}" required maxlength="13"
               placeholder="N01-23-456789" style="text-transform:uppercase" inputmode="text" autocomplete="off"
               pattern="^[A-Za-z]\d{2}-\d{2}-\d{6}$" title="LTO format A00-00-000000, e.g. N01-23-456789">
        <small class="hint">LTO format: 1 letter + 2 digits, dash, 2 digits, dash, 6 digits. Hyphens are added automatically.</small></div>
      <div class="field"><label for="vehicle_id">Assigned vehicle</label>
        <select id="vehicle_id" name="vehicle_id">
          <option value="">None</option>
          @foreach ($vehicles as $v)<option value="{{ $v->id }}" @selected((string) old('vehicle_id', $driver->vehicle_id) === (string) $v->id)>{{ $v->plate_number }} · {{ $v->type }}</option>@endforeach
        </select></div>
      <div class="field"><label for="user_id">Sign-in account</label>
        <select id="user_id" name="user_id">
          <option value="">Not linked</option>
          @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((string) old('user_id', $driver->user_id) === (string) $a->id)>{{ $a->name }} · {{ $a->email }}</option>@endforeach
        </select>
        <small class="hint">A Field Personnel account with the position Driver. When linked, that person can update this driver's deliveries.</small></div>
      <div class="field"><label for="status">Status</label>
        <select id="status" name="status" required>
          @foreach (\App\Models\Driver::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $driver->status) === $s)>{{ \App\Models\Driver::statusLabel($s) }}</option>@endforeach
        </select></div>
    </div>

    <div class="actions">
      <button type="submit" class="btn primary">{{ $editing ? 'Save changes' : 'Add driver' }}</button>
      <a class="btn" href="{{ route('drivers.index') }}">Cancel</a>
    </div>
  </form>
@endsection

@push('scripts')
<script>
  // --- Phone: "+63" is a fixed, uneditable prefix; the visible box only ever holds digits.
  // The hidden #phone field is what actually gets submitted, kept in sync on every keystroke,
  // so the backend rule (/^\+63\d{10}$/) never has to change.
  const phoneDigits = document.getElementById('phone_digits');
  const phoneHidden = document.getElementById('phone');

  function syncPhoneHidden() {
    phoneHidden.value = phoneDigits.value ? '+63' + phoneDigits.value : '';
  }
  phoneDigits.addEventListener('input', () => {
    phoneDigits.value = phoneDigits.value.replace(/\D/g, '').slice(0, 10); // digits only, max 10
    syncPhoneHidden();
  });
  // Pre-fill from a saved value (edit page) or a failed submit (old('phone')): show just the
  // digits after +63, so re-opening the form doesn't make it look like the prefix broke.
  (function seedPhoneDigits() {
    const saved = phoneHidden.value || '';
    phoneDigits.value = saved.startsWith('+63') ? saved.slice(3) : saved.replace(/\D/g, '').slice(0, 10);
    syncPhoneHidden();
  })();

  // --- License number: auto-insert the two hyphens as the user types, matching
  // A00-00-000000 (1 letter, 2 digits, 2 digits, 6 digits — 13 characters total).
  const licenseInput = document.getElementById('license_number');
  function formatLicense(raw) {
    raw = raw.toUpperCase().replace(/[^A-Z0-9]/g, '');
    const letter = raw.slice(0, 1).replace(/[^A-Z]/g, '');
    const rest = raw.slice(1).replace(/[^0-9]/g, '').slice(0, 10); // 2 + 2 + 6 digits
    const part1 = rest.slice(0, 2), part2 = rest.slice(2, 4), part3 = rest.slice(4, 10);
    return [letter + part1, part2, part3].filter(p => p !== '').join('-').replace(/-$/, '');
  }
  licenseInput.addEventListener('input', () => {
    const caretAtEnd = licenseInput.selectionStart === licenseInput.value.length;
    licenseInput.value = formatLicense(licenseInput.value);
    if (caretAtEnd) licenseInput.setSelectionRange(licenseInput.value.length, licenseInput.value.length);
  });
  licenseInput.value = formatLicense(licenseInput.value); // reformat any pre-filled/old() value too
</script>
@endpush
