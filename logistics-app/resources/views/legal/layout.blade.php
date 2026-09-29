<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') – Logistics</title>
<style>
  :root { --bg:#f3f5f9; --card:#fff; --text:#1b2333; --muted:#6b7690; --border:#d9dfeb; --primary:#2454e6; }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#0e1424; --card:#171f35; --text:#e8ecf6; --muted:#9aa6c4; --border:#2a3556; --primary:#4f7bff; }
  }
  * { box-sizing: border-box; }
  body { margin:0; background:var(--bg); color:var(--text); font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; line-height:1.65; }
  main { max-width:760px; margin:0 auto; padding:40px 16px 64px; }
  article { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:32px; }
  h1 { margin:0 0 4px; font-size:1.75rem; }
  h2 { font-size:1.1rem; margin:28px 0 8px; }
  p, li { font-size:.95rem; }
  .updated { color:var(--muted); font-size:.85rem; margin:0 0 20px; }
  a { color:var(--primary); }
  .back { display:inline-block; margin-bottom:16px; font-size:.9rem; text-decoration:none; }
  @media (max-width:600px) { article { padding:20px; } }
</style>
</head>
<body>
  <main>
    <a class="back" href="{{ route('login') }}">← Back to sign in</a>
    <article>
      <h1>@yield('title')</h1>
      <p class="updated">Last updated: September 23, 2026</p>
      @yield('content')
    </article>
  </main>
</body>
</html>
