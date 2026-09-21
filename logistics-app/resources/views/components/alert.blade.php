@props(['type' => 'success'])

@once
<style>
  .alert { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:10px 14px; margin-bottom:16px; border-radius:8px; border-left:4px solid; font-size:.9rem;
           color:var(--text, inherit); transition:opacity .3s ease, transform .3s ease; }
  .alert-success { border-color:#1a8a4a; background:rgba(26,138,74,.14); }
  .alert-warning { border-color:#d98a00; background:rgba(217,138,0,.16); }
  .alert-error   { border-color:#d13438; background:rgba(209,52,56,.14); }
  .alert-out { opacity:0; transform:translateY(-6px); }
  .alert-close { background:none; border:0; color:inherit; opacity:.6; font-size:1.2rem; line-height:1; cursor:pointer; padding:0 2px; }
  .alert-close:hover { opacity:1; }
</style>
<script>
  // Auto-dismiss every [data-alert] 5s after it appears, including alerts added to the page later.
  (function () {
    const DELAY = 5000, FADE = 300;

    function dismiss(el) {
      if (el._gone) return;
      el._gone = true;
      clearTimeout(el._timer);
      el.classList.add('alert-out');
      setTimeout(() => el.remove(), FADE);
    }
    function arm(el) {
      if (el._armed) return;
      el._armed = true;
      el._timer = setTimeout(() => dismiss(el), DELAY);
      el.querySelector('[data-alert-close]')?.addEventListener('click', () => dismiss(el));
    }
    function scan(root) {
      if (root.matches?.('[data-alert]')) arm(root);
      root.querySelectorAll?.('[data-alert]').forEach(arm);
    }
    document.addEventListener('DOMContentLoaded', () => {
      scan(document);
      new MutationObserver((muts) => muts.forEach((m) => m.addedNodes.forEach((n) => n.nodeType === 1 && scan(n))))
        .observe(document.body, { childList: true, subtree: true });
    });
  })();
</script>
@endonce

<div class="alert alert-{{ $type }}" data-alert role="{{ $type === 'error' ? 'alert' : 'status' }}">
  <span>{{ $slot }}</span>
  <button type="button" class="alert-close" data-alert-close aria-label="Dismiss">&times;</button>
</div>
