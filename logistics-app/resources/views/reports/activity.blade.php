@extends('layouts.app')
@section('title', 'Activity Log')
@section('heading', 'Activity Log')

@push('head')
<style>
  .filters { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; align-items:end; margin-bottom:16px; }
  .filters label { display:block; font-size:.75rem; font-weight:600; color:var(--muted); margin-bottom:4px; }
  .filters input, .filters select { width:100%; padding:8px 9px; border:1px solid var(--border); border-radius:8px; background:transparent; color:var(--text); font:inherit; font-size:.88rem; }
  .filters .buttons { display:flex; gap:8px; }
  .top-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:space-between; align-items:center; margin-bottom:16px; }
  .top-actions p { margin:0; color:var(--muted); font-size:.9rem; }
  a.btn { text-decoration:none; display:inline-block; }
  .empty { text-align:center; color:var(--muted); padding:32px 8px; }
  .pager { display:flex; justify-content:space-between; margin-top:14px; }
  td.desc { white-space:normal; min-width:260px; }
  td.time { white-space:nowrap; color:var(--muted); font-size:.82rem; }
</style>
@endpush

@section('content')
  @php $qs = array_filter($filters, fn ($v) => $v !== null && $v !== ''); @endphp
  <div class="card">
    <div class="top-actions">
      <p>Every sign-in, change and deletion is recorded here. Entries can't be edited or removed.</p>
      @can('export-activity-logs')
        <div style="display:flex;gap:8px">
          <a class="btn" href="{{ route('reports.activity.export', $qs) }}">Export CSV</a>
          <a class="btn primary" href="{{ route('reports.activity.print', $qs) }}" target="_blank" rel="noopener">Print</a>
        </div>
      @endcan
    </div>

    @if ($errors->any())
      <x-alert type="error">{{ $errors->first() }}</x-alert>
    @endif

    <form class="filters" method="GET" action="{{ route('reports.activity') }}">
      <div><label for="q">Search</label><input id="q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Description or user"></div>
      <div><label for="action">Action</label>
        <select id="action" name="action"><option value="">All actions</option>
          @foreach (\App\Models\ActivityLog::ACTIONS as $key => [$label])<option value="{{ $key }}" @selected($filters['action'] === $key)>{{ $label }}</option>@endforeach
        </select></div>
      <div><label for="user">User</label>
        <select id="user" name="user"><option value="">All users</option>
          @foreach ($users as $u)<option value="{{ $u->id }}" @selected((string) $filters['user'] === (string) $u->id)>{{ $u->name }}</option>@endforeach
        </select></div>
      <div><label for="from">From</label><input id="from" type="date" name="from" value="{{ $filters['from'] }}"></div>
      <div><label for="to">To</label><input id="to" type="date" name="to" value="{{ $filters['to'] }}"></div>
      <div class="buttons"><button type="submit" class="btn primary sm">Filter</button><a class="btn sm" href="{{ route('reports.activity') }}">Reset</a></div>
    </form>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Timestamp</th><th>User</th><th>Role</th><th>Action</th><th>Description</th></tr></thead>
        <tbody>
          @forelse ($logs as $log)
            <tr>
              <td class="time">{{ $log->created_at->format('M j, Y g:i:s A') }}</td>
              <td><strong>{{ $log->user_name }}</strong></td>
              <td>{{ $log->user_role ?? '—' }}</td>
              <td><span class="badge {{ \App\Models\ActivityLog::actionBadge($log->action) }}">{{ \App\Models\ActivityLog::actionLabel($log->action) }}</span></td>
              <td class="desc">{{ $log->description }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty">No activity matches these filters.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($logs->hasPages())
      <div class="pager">
        @if ($logs->previousPageUrl()) <a class="btn sm" href="{{ $logs->previousPageUrl() }}">Newer</a> @else <span></span> @endif
        @if ($logs->nextPageUrl()) <a class="btn sm" href="{{ $logs->nextPageUrl() }}">Older</a> @endif
      </div>
    @endif
  </div>
@endsection
