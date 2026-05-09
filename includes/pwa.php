<?php
/**
 * /includes/pwa.php — PWA Meta Tags & Install Banner
 * Include in <head> for meta tags, at end of <body> for banner + SW JS.
 *
 * Usage:
 *   In <head>:   <?php pwa_head(); ?>
 *   Before </body>: <?php pwa_body(); ?>
 */

function pwa_head(): void { ?>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Solen">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#07070f">
<link rel="apple-touch-icon" sizes="192x192" href="/assets/icon-192.png">
<link rel="apple-touch-icon" sizes="128x128" href="/assets/icon-128.png">
<style>
/* ── PWA NATIVE MOBILE BASE ───────────────────────────────────────────────── */
:root {
  --sat: env(safe-area-inset-top, 0px);
  --sab: env(safe-area-inset-bottom, 0px);
}
html { height: 100%; }
body {
  -webkit-tap-highlight-color: transparent;
  overscroll-behavior: none;
}
button, a, [role="button"] {
  -webkit-tap-highlight-color: transparent;
  touch-action: manipulation;
}
textarea, input[type="text"], input[type="email"], input[type="password"] {
  -webkit-appearance: none;
  font-size: 16px; /* prevents iOS auto-zoom */
}

/* ── PWA INSTALL BANNER ───────────────────────────────────────────────────── */
#pwa-install-banner {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  z-index: 9999;
  padding-bottom: max(20px, env(safe-area-inset-bottom));
  transform: translateY(110%);
  transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
  pointer-events: none;
}
#pwa-install-banner.visible {
  transform: translateY(0);
  pointer-events: all;
}
.pwa-inner {
  margin: 0 12px;
  background: linear-gradient(135deg, #0d0d1e 0%, #141428 100%);
  border: 1px solid rgba(184,149,106,0.25);
  border-radius: 24px 24px 20px 20px;
  padding: 20px 20px 24px;
  box-shadow: 0 -10px 60px rgba(0,0,0,0.7), 0 0 0 1px rgba(184,149,106,0.06);
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.pwa-row {
  display: flex;
  align-items: center;
  gap: 14px;
}
.pwa-icon {
  width: 56px; height: 56px;
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(184,149,106,.15), rgba(184,149,106,.3));
  border: 1px solid rgba(184,149,106,0.3);
  display: flex; align-items: center; justify-content: center;
  font-size: 26px;
  flex-shrink: 0; overflow: hidden;
}
.pwa-icon img { width: 100%; height: 100%; object-fit: cover; border-radius: 13px; }
.pwa-info { flex: 1; min-width: 0; }
.pwa-title {
  font-family: 'Playfair Display', Georgia, serif;
  font-size: 17px; font-weight: 500;
  color: #f2ede8; letter-spacing: 0.01em;
}
.pwa-sub {
  font-size: 12px;
  color: rgba(242,237,232,0.45);
  margin-top: 3px; line-height: 1.5;
}
.pwa-x {
  width: 28px; height: 28px; min-height: unset; padding: 0;
  border-radius: 50%;
  background: rgba(255,255,255,0.07); border: none;
  color: rgba(242,237,232,0.4);
  font-size: 16px; line-height: 1;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
  transition: background .2s;
}
.pwa-x:hover { background: rgba(255,255,255,0.15); }
.pwa-btns { display: flex; gap: 10px; }
.pwa-btn-yes {
  flex: 1; min-height: unset;
  background: #b8956a; color: #1a1008;
  border: none; border-radius: 50px;
  font-size: 15px; font-weight: 600;
  padding: 13px 20px;
  cursor: pointer;
  font-family: 'Outfit', sans-serif;
  transition: opacity .2s, transform .1s;
  animation: pwa-pulse 2.5s ease-in-out 1.5s 3;
}
.pwa-btn-yes:active { transform: scale(0.97); opacity: 0.9; }
.pwa-btn-no {
  min-height: unset;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.08);
  color: rgba(242,237,232,0.45);
  border-radius: 50px;
  font-size: 14px; padding: 13px 20px;
  cursor: pointer; white-space: nowrap;
  font-family: 'Outfit', sans-serif;
  transition: background .2s;
}
.pwa-btn-no:hover { background: rgba(255,255,255,0.1); }
#pwa-ios-hint {
  display: none;
  font-size: 12px; line-height: 1.8;
  color: rgba(242,237,232,0.4);
  text-align: center;
}
@keyframes pwa-pulse {
  0%,100% { box-shadow: 0 0 0 0 rgba(184,149,106,0.5); }
  50%      { box-shadow: 0 0 0 10px rgba(184,149,106,0); }
}
</style>
<?php }

function pwa_body(): void { ?>
<!-- PWA Install Banner -->
<div id="pwa-install-banner" role="dialog" aria-label="Install Solen app">
  <div class="pwa-inner">
    <div class="pwa-row">
      <div class="pwa-icon">
        <img src="/assets/icon-192.png" alt="" onerror="this.parentElement.innerHTML='🌿'">
      </div>
      <div class="pwa-info">
        <div class="pwa-title">Add Solen to Home Screen</div>
        <div class="pwa-sub">Your coach, always one tap away — works offline too.</div>
      </div>
      <button class="pwa-x" id="pwa-x" aria-label="Dismiss">×</button>
    </div>
    <div class="pwa-btns" id="pwa-native">
      <button class="pwa-btn-yes" id="pwa-yes">📲 Install App</button>
      <button class="pwa-btn-no"  id="pwa-no">Not now</button>
    </div>
    <div id="pwa-ios-hint">
      Tap&nbsp;<strong>📤 Share</strong>&nbsp;then&nbsp;<strong>"Add to Home Screen"</strong>&nbsp;to install Solen
    </div>
  </div>
</div>

<script>
/* ── Service Worker ────────────────────────────────────────────────────────── */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('/sw.js', { scope: '/' })
      .then(function(r){ console.log('[SW] scope:', r.scope); })
      .catch(function(e){ console.warn('[SW] failed:', e); });
  });
}

/* ── Install Banner ────────────────────────────────────────────────────────── */
(function() {
  var SNOOZE_KEY = 'solen_pwa_snooze';
  var snooze = localStorage.getItem(SNOOZE_KEY);
  // Snooze 7 days after dismiss
  if (snooze && (Date.now() - parseInt(snooze)) < 7*24*60*60*1000) return;

  // Already installed as PWA?
  if (window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true) return;

  var banner = document.getElementById('pwa-install-banner');
  var btnYes = document.getElementById('pwa-yes');
  var btnNo  = document.getElementById('pwa-no');
  var btnX   = document.getElementById('pwa-x');
  var native = document.getElementById('pwa-native');
  var iosHint= document.getElementById('pwa-ios-hint');
  var deferred = null;

  function show() { banner.classList.add('visible'); }
  function hide(snooze_it) {
    banner.classList.remove('visible');
    if (snooze_it) localStorage.setItem(SNOOZE_KEY, Date.now().toString());
  }

  /* Android / Chrome Desktop — beforeinstallprompt */
  window.addEventListener('beforeinstallprompt', function(e) {
    e.preventDefault();
    deferred = e;
    native.style.display = 'flex';
    iosHint.style.display = 'none';
    setTimeout(show, 3000); // small delay feels less aggressive
  });

  /* iOS Safari — show manual guide */
  var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
  var isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
  if (isIOS && isSafari) {
    native.style.display = 'none';
    iosHint.style.display = 'block';
    setTimeout(show, 4000);
  }

  /* Install click */
  btnYes && btnYes.addEventListener('click', function() {
    if (!deferred) return;
    deferred.prompt();
    deferred.userChoice.then(function(c) {
      deferred = null;
      hide(true);
    });
  });

  /* Dismiss */
  btnNo && btnNo.addEventListener('click', function() { hide(true); });
  btnX  && btnX.addEventListener('click',  function() { hide(true); });

  /* After install */
  window.addEventListener('appinstalled', function() {
    hide(true);
    console.log('[PWA] Installed!');
  });
})();
</script>
<?php }
