@extends('layouts.app')
@section('title', 'Driver dashboard')
@section('heading', 'Driver dashboard')

@push('head')
<style>
  .sc-intro { color:var(--muted); font-size:.9rem; margin:0 0 20px; line-height:1.6; }
  .sc-intro code { background:var(--hover); padding:1px 5px; border-radius:4px; }
  .sc-section { margin-bottom:20px; }
  .sc-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:20px; align-items:start; }
  .sc-form label { display:block; font-size:.8rem; font-weight:600; margin-bottom:5px; }
  .sc-form input, .sc-form textarea { width:100%; padding:9px 11px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.92rem; }
  .sc-form textarea { min-height:220px; resize:vertical; line-height:1.5; }
  .sc-form .field { margin-bottom:12px; }
  .sc-form .actions { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
  .sc-meta { color:var(--muted); font-size:.78rem; }
  .sc-preview { padding:14px 16px; border:1px dashed var(--border); border-radius:10px; font-size:.9rem; line-height:1.6; }
  .sc-preview-label { font-size:.7rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); margin:0 0 6px; }
  .sc-preview h3 { margin:0 0 8px; font-size:1rem; }
  .sc-preview ul { margin:0 0 8px; padding-left:20px; }
  .sc-preview p { margin:0 0 8px; }
  .errors { color:#d13438; font-size:.85rem; margin:0 0 14px; padding-left:18px; }
  @media (max-width:900px) { .sc-grid { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
  @if ($errors->any())
    <ul class="errors">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  @endif

  <p class="sc-intro">
    These sections appear on the dashboard of every Field Personnel account (drivers and helpers).
    Write plain text: start a line with <code>- </code> to make a bullet point, and leave a blank line between paragraphs.
  </p>

  @foreach ($sections as $s)
    <section class="card sc-section" id="section-{{ $s->key }}">
      <div class="sc-grid">
        <form class="sc-form" method="POST" action="{{ route('site-contents.update', $s) }}">
          @csrf @method('PUT')
          <div class="field">
            <label for="title-{{ $s->key }}">Section title</label>
            <input id="title-{{ $s->key }}" name="title" value="{{ $s->title }}" required maxlength="100">
          </div>
          <div class="field">
            <label for="body-{{ $s->key }}">Text</label>
            <textarea id="body-{{ $s->key }}" name="body" required maxlength="5000">{{ $s->body }}</textarea>
          </div>
          <div class="actions">
            <span class="sc-meta">Last saved {{ $s->updated_at->diffForHumans() }}@if ($s->editor) by {{ $s->editor->name }}@endif</span>
            <button type="submit" class="btn primary">Save</button>
          </div>
        </form>
        <div>
          <p class="sc-preview-label">How drivers see it</p>
          <div class="sc-preview">
            <h3>{{ $s->title }}</h3>
            {{ $s->bodyHtml() }}
          </div>
        </div>
      </div>
    </section>
  @endforeach
@endsection
