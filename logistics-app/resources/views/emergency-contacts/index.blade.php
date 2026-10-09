@extends('layouts.app')
@section('title', 'Emergency contacts')
@section('heading', 'Emergency contacts')

@push('head')
<style>
  .ec-intro { color:var(--muted); font-size:.9rem; margin:0 0 16px; }
  .ec-table input, .ec-table select, .ec-add input, .ec-add select { width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.9rem; }
  .ec-table td { vertical-align:middle; }
  .ec-table .order { width:70px; }
  .ec-add { display:grid; grid-template-columns:2fr 1.4fr 1.4fr 80px auto; gap:8px; align-items:end; }
  .ec-add label { display:block; font-size:.78rem; font-weight:600; margin-bottom:4px; }
  .ec-actions { display:flex; gap:6px; }
  .ec-table input.ec-invalid { border-color:#d13438; }
  .ec-bad { display:block; margin-top:4px; color:#d13438; font-size:.75rem; }
  .errors { color:#d13438; font-size:.85rem; margin:0 0 14px; padding-left:18px; }
  @media (max-width:760px) { .ec-add { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
  @if ($errors->any())
    <ul class="errors">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  @endif

  <div class="card" style="margin-bottom:24px">
    <h2>Add a contact</h2>
    <p class="ec-intro">These numbers appear under the red Emergency button on drivers' screens. One tap on a phone calls the number.<br>
      Philippine numbers only: mobile <strong>0917 123 4567</strong> or <strong>+63 917 123 4567</strong>, landline <strong>(02) 8123-4567</strong>, or a hotline like <strong>911</strong>.</p>
    <form class="ec-add" method="POST" action="{{ route('emergency-contacts.store') }}">
      @csrf
      <div><label for="new_name">Name</label><input id="new_name" name="name" value="{{ old('name') }}" required maxlength="100" placeholder="e.g. Dispatch office"></div>
      <div><label for="new_phone">Phone</label><input id="new_phone" name="phone" data-ph-phone value="{{ old('phone') }}" required maxlength="20" inputmode="tel" placeholder="e.g. +639171234567"></div>
      <div><label for="new_category">Type</label>
        <select id="new_category" name="category" required>
          @foreach (\App\Models\EmergencyContact::CATEGORIES as $value => $label)
            <option value="{{ $value }}" @selected(old('category', 'company') === $value)>{{ $label }}</option>
          @endforeach
        </select></div>
      <div><label for="new_order">Order</label><input id="new_order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', 0) }}" title="Lower numbers are listed first within their type"></div>
      <button type="submit" class="btn primary">Add</button>
    </form>
  </div>

  <div class="card">
    <h2>All contacts</h2>
    <div class="table-wrap">
      <table class="ec-table">
        <thead><tr><th>Name</th><th>Phone</th><th>Type</th><th>Order</th><th></th></tr></thead>
        <tbody>
          @forelse ($contacts as $c)
            <tr>
              <td><input form="ec-{{ $c->id }}" name="name" value="{{ $c->name }}" required maxlength="100" aria-label="Name"></td>
              <td><input form="ec-{{ $c->id }}" name="phone" data-ph-phone value="{{ $c->phone }}" required maxlength="20" inputmode="tel" aria-label="Phone"
                         @unless (\App\Models\EmergencyContact::isValidPhilippineNumber($c->phone)) class="ec-invalid" aria-describedby="ec-bad-{{ $c->id }}" @endunless>
                @unless (\App\Models\EmergencyContact::isValidPhilippineNumber($c->phone))
                  <small class="ec-bad" id="ec-bad-{{ $c->id }}">Not a valid Philippine number. Please correct it.</small>
                @endunless
              </td>
              <td>
                <select form="ec-{{ $c->id }}" name="category" aria-label="Type">
                  @foreach (\App\Models\EmergencyContact::CATEGORIES as $value => $label)
                    <option value="{{ $value }}" @selected($c->category === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </td>
              <td class="order"><input form="ec-{{ $c->id }}" name="sort_order" type="number" min="0" max="999" value="{{ $c->sort_order }}" aria-label="Order"></td>
              <td>
                <div class="ec-actions">
                  <form id="ec-{{ $c->id }}" method="POST" action="{{ route('emergency-contacts.update', $c) }}">@csrf @method('PUT')
                    <button type="submit" class="btn sm primary">Save</button>
                  </form>
                  <form method="POST" action="{{ route('emergency-contacts.destroy', $c) }}" onsubmit="return confirm('Remove {{ e(addslashes($c->name)) }}?')">@csrf @method('DELETE')
                    <button type="submit" class="btn sm">Remove</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:24px">No contacts yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  // Philippine phone boxes: only digits, spaces, dashes, brackets and a leading +; no digits
  // beyond the longest valid number for how it starts; and the full format checked before
  // the form is sent. The server applies the same rule (EmergencyContact::isValidPhilippineNumber).
  (function () {
    const VALID = /^(?:09\d{9}|\+639\d{9}|0[2-8]\d{8}|\+63[2-8]\d{8}|[1-9]\d{2,4}|1800\d{7,9})$/;
    const MESSAGE = 'Enter a valid Philippine number: a mobile like 0917 123 4567 or +63 917 123 4567, a landline like (02) 8123-4567, or a hotline like 911.';

    // Numbers that could still become valid as more digits are typed: the start of a
    // mobile, landline, +63 number, hotline or 1800 number, never longer than its full form.
    const CAN_BECOME_VALID = /^(?:\+(?:6(?:3(?:9\d{0,9}|[2-8]\d{0,8})?)?)?|0(?:9\d{0,9}|[2-8]\d{0,8})?|1800\d{0,9}|[1-9]\d{0,4})$/;
    const dialable = (value) => (value.trim().startsWith('+') ? '+' : '') + value.replace(/\D/g, '');

    // Keep a character only if the number can still become valid with it.
    function clean(value) {
      let out = '';
      for (const ch of value) {
        if (/[\s()\-]/.test(ch)) { out += ch; continue; }
        if ((/\d/.test(ch) || ch === '+') && CAN_BECOME_VALID.test(dialable(out + ch))) out += ch;
      }
      return out;
    }

    function check(input) {
      input.setCustomValidity(input.value === '' || VALID.test(dialable(input.value)) ? '' : MESSAGE);
    }

    document.querySelectorAll('input[data-ph-phone]').forEach((input) => {
      input.addEventListener('input', () => {
        const at = input.selectionStart, before = input.value.length;
        const cleaned = clean(input.value);
        if (cleaned !== input.value) {
          input.value = cleaned;
          const pos = Math.max(0, at - (before - cleaned.length));
          input.setSelectionRange(pos, pos);
        }
        check(input);
      });
      check(input);
    });
  })();
</script>
@endpush
