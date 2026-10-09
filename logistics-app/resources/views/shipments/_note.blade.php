{{--
  One note in a shipment's notes thread. Used on the page and for notes loaded live.
  $n: ShipmentNote (with user). Its created_at is the server time it was posted.
  The relative time ("2 minutes ago") is kept current in the browser; the exact time shows beside it.
--}}
<li class="{{ (int) $n->user_id === (int) auth()->id() ? 'mine' : '' }}" data-note-id="{{ $n->id }}">
  <span class="avatar">
    @if ($n->user?->avatarUrl()) <img src="{{ $n->user->avatarUrl() }}" alt=""> @else {{ $n->user?->initials() }} @endif
  </span>
  <div class="note-body">
    <div class="note-meta">
      <strong>{{ $n->user?->name ?? __('Former user') }}</strong>
      <span>{{ $n->user?->roleLabel(true) }}</span>
      <span class="note-time">
        <time datetime="{{ $n->created_at->toIso8601String() }}" data-relative>{{ $n->created_at->diffForHumans() }}</time>
        · {{ $n->created_at->translatedFormat($n->created_at->isToday() ? 'g:i A' : 'M j, Y, g:i A') }}
      </span>
    </div>
    <p>{{ $n->body }}</p>
  </div>
</li>
