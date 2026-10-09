@extends('layouts.app')
@php $editing = $helper->exists; @endphp
@section('title', $editing ? 'Edit helper' : 'Add helper')
@section('heading', $editing ? 'Edit helper' : 'Add helper')

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
  <form class="card" method="POST" action="{{ $editing ? route('helpers.update', $helper) : route('helpers.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
      <ul class="errors">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif

    <div class="form-grid">
      <div class="field"><label for="name">Full name</label>
        <input id="name" name="name" value="{{ old('name', $helper->name) }}" required maxlength="255"></div>
      <div class="field"><label for="phone_digits">Phone</label>
        <div class="phone-mask">
          <span class="prefix" aria-hidden="true">+63</span>
          <input type="text" id="phone_digits" inputmode="numeric" autocomplete="off" maxlength="10" placeholder="9171234567"
                 aria-label="Mobile number, 10 digits, without +63">
        </div>
        <input type="hidden" id="phone" name="phone" value="{{ old('phone', $helper->phone) }}">
        <small class="hint">Enter the 10 digits after +63, e.g. 9171234567 for +639171234567.</small></div>
      <div class="field"><label for="vehicle_id">Assigned truck</label>
        <select id="vehicle_id" name="vehicle_id">
          <option value="">None</option>
          @foreach ($vehicles as $v)<option value="{{ $v->id }}" @selected((string) old('vehicle_id', $helper->vehicle_id) === (string) $v->id)>{{ $v->plate_number }} · {{ $v->type }}</option>@endforeach
        </select>
        <small class="hint">The truck this helper usually rides with.</small></div>
      <div class="field"><label for="user_id">Sign-in account</label>
        <select id="user_id" name="user_id">
          <option value="">Not linked</option>
          @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected((string) old('user_id', $helper->user_id) === (string) $a->id)>{{ $a->name }} · {{ $a->email }}</option>@endforeach
        </select>
        <small class="hint">A Field Personnel account with the position Helper.</small></div>
      <div class="field"><label for="status">Status</label>
        <select id="status" name="status" required>
          @foreach (\App\Models\Driver::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $helper->status) === $s)>{{ \App\Models\Driver::statusLabel($s) }}</option>@endforeach
        </select></div>
    </div>

    <div class="actions">
      <button type="submit" class="btn primary">{{ $editing ? 'Save changes' : 'Add helper' }}</button>
      <a class="btn" href="{{ route('drivers.index') }}">Cancel</a>
    </div>
  </form>
@endsection

@push('scripts')
<script>
  // "+63" is a fixed, uneditable prefix; the visible box only ever holds digits. The hidden
  // #phone field is what gets submitted, kept in sync on every keystroke.
  const phoneDigits = document.getElementById('phone_digits');
  const phoneHidden = document.getElementById('phone');

  function syncPhoneHidden() {
    phoneHidden.value = phoneDigits.value ? '+63' + phoneDigits.value : '';
  }
  phoneDigits.addEventListener('input', () => {
    phoneDigits.value = phoneDigits.value.replace(/\D/g, '').slice(0, 10); // digits only, max 10
    syncPhoneHidden();
  });
  // Pre-fill from a saved value (edit page) or a failed submit: show just the digits after +63.
  (function seedPhoneDigits() {
    const saved = phoneHidden.value || '';
    phoneDigits.value = saved.startsWith('+63') ? saved.slice(3) : saved.replace(/\D/g, '').slice(0, 10);
    syncPhoneHidden();
  })();
</script>
@endpush
