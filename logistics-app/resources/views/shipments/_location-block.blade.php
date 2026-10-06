{{--
  One "smart location" field: a search-first box that resolves City/Province/Postal from
  the Philippine locations dataset, plus an always-reachable manual mode for anything not
  in it. Used for both Origin and Destination — $prefix is "origin" or "destination".

  Three views, one visible at a time:
    #{prefix}_loc_search   – the search box
    #{prefix}_loc_resolved – a summary card once a city is picked
    #{prefix}_loc_manual   – the three real, always-submitted inputs
  A failed-validation reload shows the card when old() has a complete pick, the manual
  fields when it has a partial one, and the search box otherwise.
--}}
@php
    $oldCity = old("{$prefix}_city");
    $oldProvince = old("{$prefix}_province");
    $oldPostal = old("{$prefix}_postal_code");
    $startView = filled($oldCity) ? (filled($oldProvince) && filled($oldPostal) ? 'resolved' : 'manual') : 'search';
@endphp

<div class="field full loc-block" id="{{ $prefix }}_loc_block">
  <div class="loc-label-row">
    <label for="{{ $prefix }}_loc_query">Location</label>
    <button type="button" class="loc-link-btn" id="{{ $prefix }}_loc_manual_btn" @if ($startView === 'manual') hidden @endif>Enter manually</button>
    <button type="button" class="loc-link-btn" id="{{ $prefix }}_loc_search_btn" @unless ($startView === 'manual') hidden @endunless>Search instead</button>
  </div>

  <div class="loc-search" id="{{ $prefix }}_loc_search" @unless ($startView === 'search') hidden @endunless>
    <div class="loc-search-box combo">
      <svg class="loc-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
      </svg>
      <input type="text" id="{{ $prefix }}_loc_query" placeholder="Search city or municipality, e.g. Quezon City" autocomplete="off"
             role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="{{ $prefix }}_loc_list"
             aria-describedby="{{ $prefix }}_loc_hint">
      <span class="loc-spinner" id="{{ $prefix }}_loc_spinner" hidden></span>
      <button type="button" class="loc-clear" id="{{ $prefix }}_loc_clear" aria-label="Clear search" hidden>&times;</button>
      <ul class="combo-list" id="{{ $prefix }}_loc_list" role="listbox" hidden></ul>
    </div>
    <small class="loc-hint" id="{{ $prefix }}_loc_hint">Province and postal code fill in automatically.</small>
  </div>

  <div class="loc-resolved-card" id="{{ $prefix }}_loc_resolved" @unless ($startView === 'resolved') hidden @endunless>
    <svg class="loc-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
    </svg>
    <div class="loc-resolved-body" aria-live="polite">
      <span class="loc-resolved-city" id="{{ $prefix }}_loc_resolved_city">{{ $oldCity }}</span>
      <span class="loc-resolved-sub" id="{{ $prefix }}_loc_resolved_sub">{{ $oldProvince }}@if (filled($oldPostal)) · {{ $oldPostal }}@endif</span>
    </div>
    <div class="loc-resolved-actions">
      <button type="button" class="btn sm" id="{{ $prefix }}_loc_change_btn">Change</button>
      <button type="button" class="btn sm" id="{{ $prefix }}_loc_edit_btn">Edit</button>
    </div>
  </div>

  {{-- Places found in the Address field: filled in when there is one clear match, offered otherwise. --}}
  <div class="loc-suggest" id="{{ $prefix }}_loc_suggest" aria-live="polite" hidden></div>

  <div class="loc-manual" id="{{ $prefix }}_loc_manual" @unless ($startView === 'manual') hidden @endunless>
    <div class="form-grid loc-manual-grid">
      <div class="field"><label for="{{ $prefix }}_city">City / municipality</label>
        <input id="{{ $prefix }}_city" name="{{ $prefix }}_city" value="{{ $oldCity }}" required autocomplete="off"></div>
      <div class="field"><label for="{{ $prefix }}_province">Province</label>
        <input id="{{ $prefix }}_province" name="{{ $prefix }}_province" value="{{ $oldProvince }}" autocomplete="off"></div>
      <div class="field"><label for="{{ $prefix }}_postal_code">Postal code</label>
        <input id="{{ $prefix }}_postal_code" name="{{ $prefix }}_postal_code" value="{{ $oldPostal }}" autocomplete="off" inputmode="numeric" maxlength="10"></div>
    </div>
  </div>
</div>
