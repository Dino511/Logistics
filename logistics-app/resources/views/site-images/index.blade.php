@extends('layouts.app')
@section('title', 'Site Images')
@section('heading', 'Site Images')

@section('content')
  @if ($errors->any())
    <x-alert type="error">{{ $errors->first() }}</x-alert>
  @endif

  @foreach ($slots as $name => $slot)
    @php $image = $images->get($name); @endphp
    <div class="card" style="margin-bottom:20px; display:flex; gap:20px; align-items:center; flex-wrap:wrap;">
      <img src="{{ \App\Models\SiteImage::urlFor($name) }}" alt="" style="width:160px; height:110px; object-fit:cover; border-radius:8px; border:1px solid var(--border);">
      <div style="flex:1; min-width:220px;">
        <h2 style="margin-top:0;">{{ $slot['label'] }}</h2>
        <p style="color:var(--muted); margin:0 0 12px;">
          {{ $image ? 'Uploaded '.$image->updated_at->diffForHumans() : 'Using the default image' }}
        </p>
        <form method="POST" action="{{ route('site-images.store') }}" enctype="multipart/form-data" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
          @csrf
          <input type="hidden" name="name" value="{{ $name }}">
          <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required aria-label="New image for {{ $slot['label'] }}">
          <button type="submit" class="btn primary sm">Upload</button>
        </form>
        <small style="color:var(--muted);">JPG, PNG or WebP, up to 5 MB, at least 400×400 px.</small>
      </div>
    </div>
  @endforeach

  <div class="card">
    <h2>Profile pictures</h2>
    <p style="color:var(--muted); margin:-8px 0 16px;">Users can also change their own photo from their profile menu. JPG, PNG or WebP, up to 2 MB.</p>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Photo</th><th>User</th><th>Last updated</th><th></th></tr></thead>
        <tbody>
          @foreach ($users as $user)
            <tr>
              <td>
                <span class="avatar">
                  @if ($user->avatar)
                    <img src="{{ $user->avatar->url }}" alt="">
                  @else
                    {{ $user->initials() }}
                  @endif
                </span>
              </td>
              <td><strong>{{ $user->name }}</strong><br><small style="color:var(--muted)">{{ $user->roleLabel() }}</small></td>
              <td style="color:var(--muted)">{{ $user->avatar ? $user->avatar->updated_at->diffForHumans() : 'No photo' }}</td>
              <td>
                <div class="inline" style="flex-wrap:wrap;">
                  <form class="inline" method="POST" action="{{ route('site-images.avatar.store', $user) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required aria-label="New profile picture for {{ $user->name }}">
                    <button type="submit" class="btn primary sm">Upload</button>
                  </form>
                  @if ($user->avatar)
                    <form class="inline" method="POST" action="{{ route('site-images.avatar.destroy', $user) }}"
                          onsubmit="return confirm('Remove {{ e(addslashes($user->name)) }}\'s profile picture?')">
                      @csrf @method('DELETE')
                      <button type="submit" class="btn sm">Remove</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
