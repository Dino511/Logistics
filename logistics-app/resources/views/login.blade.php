<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logistics – Sign in</title>
@include('partials.pwa')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #f3f5f9;
    --card: #ffffff;
    --text: #1b2333;
    --muted: #6b7690;
    --border: #d9dfeb;
    --primary: #2454e6;
    --primary-hover: #1a43c4;
    --danger: #d13438;
    --panel: #12224d;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #0e1424;
      --card: #171f35;
      --text: #e8ecf6;
      --muted: #9aa6c4;
      --border: #2a3556;
      --primary: #4f7bff;
      --primary-hover: #6b91ff;
      --danger: #ff6b6f;
      --panel: #0a1230;
    }
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1fr 1fr;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
  }
  .brand {
    position: relative;
    overflow: hidden;
    background: var(--panel) url('{{ \App\Models\SiteImage::urlFor('login_background_portrait') }}') center / cover no-repeat;
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    padding: 48px;
  }
  /* Darken the artwork so the text stays readable, and fade the right edge into the page. */
  .brand::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
      linear-gradient(to bottom, rgba(8, 16, 40, .75) 0%, rgba(8, 16, 40, .3) 40%, rgba(8, 16, 40, .1) 70%, rgba(8, 16, 40, .35) 100%),
      linear-gradient(to right, transparent 85%, rgba(8, 16, 40, .35) 100%);
  }
  .brand > * { position: relative; }
  .brand-card {
    max-width: 400px;
    padding: 24px 28px;
    border-radius: 16px;
    background: rgba(8, 16, 40, .5);
    border: 1px solid rgba(255, 255, 255, .14);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, .3);
    font-family: 'Inter', system-ui, sans-serif;
    color: #fff;
  }
  .brand .logo { display: flex; align-items: center; gap: 12px; font-size: 2rem; font-weight: 800; letter-spacing: -.02em; text-shadow: 0 2px 10px rgba(0, 0, 0, .45); }
  .brand p { margin: 12px 0 0; font-size: 1.05rem; font-weight: 400; line-height: 1.65; color: #fff; text-shadow: 0 1px 6px rgba(0, 0, 0, .55); }
  @media (min-aspect-ratio: 12/5) {
    .brand { background-image: url('{{ \App\Models\SiteImage::urlFor('login_background_landscape') }}'); }
  }
  .main { display: flex; align-items: center; justify-content: center; padding: 24px; }
  form {
    width: 100%;
    max-width: 380px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 32px;
  }
  h1 { margin: 0 0 4px; font-size: 1.5rem; }
  .sub { margin: 0 0 24px; color: var(--muted); font-size: .95rem; }
  label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: 6px; }
  .field { margin-bottom: 18px; position: relative; }
  input[type=email], input[type=text], input[type=password] {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: transparent;
    color: var(--text);
    font-size: 1rem;
  }
  input:focus { outline: 2px solid var(--primary); outline-offset: 1px; border-color: var(--primary); }
  .toggle {
    position: absolute; right: 8px; top: 30px;
    background: none; border: 0; color: var(--muted);
    font-size: .8rem; cursor: pointer; padding: 6px;
  }
  .row { display: flex; justify-content: space-between; align-items: center; font-size: .875rem; margin-bottom: 20px; }
  .row label { display: flex; align-items: center; gap: 6px; font-weight: 400; margin: 0; }
  a { color: var(--primary); text-decoration: none; }
  a:hover { text-decoration: underline; }
  .link-like { background: none; border: 0; padding: 0; color: var(--primary); font: inherit; font-size: .875rem; cursor: pointer; }
  .link-like:hover { text-decoration: underline; }
  .forgot-help { margin: -8px 0 16px; padding: 10px 12px; border-radius: 8px; background: color-mix(in srgb, var(--primary) 10%, transparent); font-size: .85rem; line-height: 1.5; }
  .forgot-help[hidden] { display: none; }
  .login-lang { display: flex; justify-content: flex-end; gap: 0; margin: -12px 0 12px; }
  .login-lang button { padding: 4px 10px; border: 1px solid var(--border); background: transparent; color: var(--muted); font: inherit; font-size: .78rem; cursor: pointer; }
  .login-lang button:first-of-type { border-radius: 6px 0 0 6px; }
  .login-lang button:last-of-type { border-radius: 0 6px 6px 0; border-left: 0; }
  .login-lang button[aria-pressed="true"] { background: var(--primary); border-color: var(--primary); color: #fff; font-weight: 600; }
  button.submit {
    width: 100%; padding: 12px; border: 0; border-radius: 8px;
    background: var(--primary); color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer;
  }
  button.submit:hover { background: var(--primary-hover); }
  button.submit:disabled { opacity: .6; cursor: default; }
  .consent { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 18px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; }
  .consent input { margin: 3px 0 0; width: 16px; height: 16px; flex: 0 0 auto; accent-color: var(--primary); cursor: pointer; }
  .consent label { font-size: .8rem; font-weight: 400; line-height: 1.5; color: var(--muted); margin: 0; cursor: pointer; }
  .consent.invalid { border-color: var(--danger); }
  .error { color: var(--danger); font-size: .875rem; min-height: 1.2em; margin-bottom: 12px; }
  @media (max-width: 800px) {
    body { grid-template-columns: 1fr; }
    .brand {
      min-height: 200px;
      padding: 24px;
      background-image: url('{{ \App\Models\SiteImage::urlFor('login_background_landscape') }}');
    }
    .brand-card { padding: 14px 18px; }
    .brand .logo { font-size: 1.5rem; }
    .brand p { display: none; }
    .main { padding-top: 32px; }
  }
</style>
</head>
<body>
  <section class="brand">
    <div class="brand-card">
    <div class="logo">
      <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
      </svg>
      Logistics
    </div>
    <p>{{ __('Track shipments, manage fleets and keep every delivery on schedule from one place.') }}</p>
    </div>
  </section>

  <main class="main">
    <form id="loginForm" method="POST" action="{{ route('login') }}" novalidate>
      @csrf
      {{-- These buttons belong to #langForm (below the sign-in form): forms can't be nested. --}}
      <div class="login-lang" role="group" aria-label="{{ __('Language') }}">
        @foreach (\App\Http\Middleware\SetLocale::LOCALES as $code => $name)
          <button type="submit" form="langForm" name="locale" value="{{ $code }}" formnovalidate aria-pressed="{{ app()->getLocale() === $code ? 'true' : 'false' }}">{{ $name }}</button>
        @endforeach
      </div>
      <h1>{{ __('Welcome back') }}</h1>
      <p class="sub">{{ __('Sign in to your account to continue.') }}</p>

      <div class="field">
        <label for="email">{{ __('Email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
      </div>

      <div class="field">
        <label for="password">{{ __('Password') }}</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="button" class="toggle" id="toggle" aria-label="{{ __('Show password') }}">{{ __('Show') }}</button>
      </div>

      <div class="row">
        <label><input type="checkbox" name="remember"> {{ __('Remember me') }}</label>
        <button type="button" class="link-like" id="forgotToggle" aria-expanded="false" aria-controls="forgotHelp">{{ __('Forgot password?') }}</button>
      </div>
      {{-- Passwords are only reset by a Super Admin (Users & Roles), so point people there. --}}
      <p class="forgot-help" id="forgotHelp" hidden>
        {!! __('Passwords are reset by your system administrator. Ask a <strong>Super Admin</strong> to set a new one for you from <em>Users &amp; Roles</em>.') !!}
      </p>

      <div class="consent" id="consent">
        <input type="checkbox" id="terms" name="terms" value="1" required @checked(old('terms')) aria-describedby="error">
        <label for="terms">
          {!! __('By signing in, you agree to our :terms and acknowledge our :privacy regarding the handling of your personal information.', [
            'terms' => '<a href="'.e(route('terms')).'" target="_blank" rel="noopener">'.e(__('Terms of Service')).'</a>',
            'privacy' => '<a href="'.e(route('privacy')).'" target="_blank" rel="noopener">'.e(__('Privacy Policy')).'</a>',
          ]) !!}
        </label>
      </div>

      @if ($errors->any())
        <x-alert type="error">{{ $errors->first() }}</x-alert>
      @endif
      <div class="error" id="error" role="alert"></div>
      <button type="submit" class="submit" id="submitBtn">{{ __('Sign in') }}</button>
    </form>
    <form id="langForm" method="POST" action="{{ route('locale.update') }}" hidden>@csrf</form>
  </main>

<script>
  const L = {{ Js::from(['hide' => __('Hide'), 'show' => __('Show'), 'hidePassword' => __('Hide password'), 'showPassword' => __('Show password'), 'enterBoth' => __('Please enter your email and password.'), 'agree' => __('Please agree to the Terms of Service and Privacy Policy to sign in.'), 'signingIn' => __('Signing in…')]) }};

  const forgot = document.getElementById('forgotToggle');
  forgot.addEventListener('click', () => {
    const help = document.getElementById('forgotHelp');
    help.hidden = !help.hidden;
    forgot.setAttribute('aria-expanded', String(!help.hidden));
  });

  const form = document.getElementById('loginForm');
  const errorEl = document.getElementById('error');
  const btn = document.getElementById('submitBtn');
  const pw = document.getElementById('password');

  document.getElementById('toggle').addEventListener('click', (e) => {
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    e.target.textContent = show ? L.hide : L.show;
    e.target.setAttribute('aria-label', show ? L.hidePassword : L.showPassword);
  });

  const terms = document.getElementById('terms');
  const consent = document.getElementById('consent');
  terms.addEventListener('change', () => {
    if (terms.checked) {
      consent.classList.remove('invalid');
      errorEl.textContent = '';
    }
  });

  form.addEventListener('submit', (e) => {
    if (!form.email.value.trim() || !pw.value) {
      e.preventDefault();
      errorEl.textContent = L.enterBoth;
      return;
    }
    if (!terms.checked) {
      e.preventDefault();
      consent.classList.add('invalid');
      errorEl.textContent = L.agree;
      terms.focus();
      return;
    }
    btn.disabled = true;
    btn.textContent = L.signingIn;
  });
</script>
</body>
</html>
