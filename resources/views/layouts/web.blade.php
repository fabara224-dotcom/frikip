{{-- Mise en page de l'application web : barre du haut, onglets en bas sur téléphone. --}}
@php
    $app = config('terrain.app_name');
    $isRecruiter = ($me['role'] ?? null) === 'recruiter';
    // Défis et Coaching côte à côte dans la barre
    $tabs = [['feed', __('Fil'), 'home'], ['matches', __('Matchs'), 'score'], ['challenges', __('Défis'), 'trophy'], ['coaching', __('Coaching'), 'coach']];
    if ($me) {
        $tabs[] = $isRecruiter ? ['search', __('Recherche'), 'search'] : ['publish', __('Publier'), 'plus'];
        $tabs[] = ['me', $isRecruiter ? __('Compte') : __('Profil'), 'user'];
    } else {
        $tabs[] = ['login', __('Connexion'), 'user'];
    }
    $more = [
        ['live', __('Matchs en direct'), 'live'], ['rankings', __('Classements'), 'star'], ['events', __('Détections'), 'calendar'],
        ['clubs', __('Clubs et académies'), 'stadium'], ['stories', __('Ils ont été recrutés'), 'party'],
    ];
    if ($me) {
        $more[] = ['referral', __('Inviter mes amis'), 'gift'];
        $more[] = ['notifications', __('Notifications'), 'bell'];
        if ($isRecruiter) {
            $more[] = ['alerts', __('Mes alertes'), 'megaphone'];
            $more[] = ['clubs.edit', __('Ma page club'), 'tag'];
        } else {
            $more[] = ['stats', __('Mes statistiques'), 'stats'];
        }
        $more[] = ['verification', __('Vérifier mon compte'), 'check'];
        if ($realAccounts ?? false) {
            $more[] = ['profile.edit', __('Modifier mon profil'), 'pencil'];
        }
        $more[] = ['offers', $isRecruiter ? __('Mes offres') : __('Offres'), 'tag'];
        $more[] = ['coaching.follow', __('Mon coaching'), 'coach'];
        $more[] = ['payments.history', __('Mes paiements'), 'receipt'];
        $more[] = ['premium', __('Frikip sans pub'), 'ad', ['product' => 'sans_pub']];
    }
    $more[] = ['video.services', __('Montage vidéo'), 'film'];
    $more[] = ['structure.offer', __('Offre Structure Pro'), 'stadium'];
    $more[] = ['partners', __('Devenir partenaire'), 'handshake'];
    $more[] = ['audio-help', __('Aide audio'), 'sound'];
    $icons = [
        'home' => '<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'score' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M12 5v14M7 10v4M15.5 10h2v4h-2z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'plus' => '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/>',
        'work' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 13h18"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
        'msg' => '<path d="M4 5h16v11H8l-4 4z"/>',
        'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'trophy' => '<path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4M12 14v4M8 21h8"/>',
        'coach' => '<circle cx="9" cy="7" r="3.2"/><path d="M3 21c.5-4 2.8-6.3 6-6.3 1.5 0 2.9.5 4 1.5"/><path d="m14.5 14.5 2.2 2.2 4.3-4.7"/>',
    ];
    $current = request()->route()?->getName();
    $active = fn ($name) => $current === $name || ($name === 'matches' && str_starts_with((string) $current, 'matches')) || ($name === 'search' && $current === 'compare')
        || ($name === 'challenges' && str_starts_with((string) $current, 'challenges')) || ($name === 'coaching' && str_starts_with((string) $current, 'coaching'));
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', $app)</title>
@stack('meta')
<meta name="description" content="@yield('description', $app.' : compilations vidéo de sportifs, recrutement et scores en direct.')">
<link rel="preload" href="{{ asset('fonts/archivo-var.woff') }}" as="font" type="font/woff" crossorigin>
<link rel="stylesheet" href="{{ asset('css/terrain.css') }}">
<link rel="stylesheet" href="{{ asset('css/web.css') }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#0B2318">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('icons/favicon-32.png') }}" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="stylesheet" href="{{ asset('css/frikip.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/frikip-plus.css') }}?v=1">
</head>
<body class="webapp @yield('body-class') {{ $lite ? 'lite' : '' }}">
<header class="apptop">
  <div class="apptop-in gut">
    <a class="logo" href="{{ route('feed') }}" aria-label="{{ $app }}"><img src="{{ asset('brand/logo.svg') }}" alt="{{ $app }}" width="112" height="30"></a>
    <nav class="appnav" aria-label="Application">
      @foreach ($tabs as [$name, $label, $icon])
        <a href="{{ route($name) }}" @if ($active($name)) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>
    <div class="apptop-actions">
      <details class="moremenu">
        <summary class="iconbtn" aria-label="{{ __('Plus') }}" title="{{ __('Plus') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg></summary>
        <nav class="card" aria-label="{{ __('Plus') }}">
          @foreach ($more as $item)
            <a href="{{ route($item[0], $item[3] ?? []) }}"><x-ico :name="$item[2]" />{{ $item[1] }}</a>
          @endforeach
          <a href="{{ rtrim(config('terrain.shop_url'), '/') }}/?utm_source=frikip&utm_medium=menu" target="_blank" rel="noopener"><x-ico name="tag" />{{ __('Boutique :shop (impression, maillots)', ['shop' => config('terrain.shop_name', 'Fabara')]) }}</a>
          <label class="countrysel"><x-ico name="stadium" /><span>{{ __('Pays') }}</span>
            <select aria-label="{{ __('Pays') }}" onchange="location.href=@js(url('/pays')) + '/' + encodeURIComponent(this.value)">
              @foreach (\App\Terrain\Region::names() as $c)<option value="{{ $c }}" @selected(\App\Terrain\Region::viewer() === $c)>{{ \App\Terrain\DataLang::t($c) }}</option>@endforeach
            </select></label>
          <div class="langs">
            @foreach (config('terrain.locales') as $code => $label)
              <a href="{{ route('locale', $code) }}" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
          </div>
          <label class="check litetoggle"><input type="checkbox" id="lite-toggle" @checked($lite)> {{ __('Économie de données (3G)') }}</label>
        </nav>
      </details>
      @if ($me)
        <a class="iconbtn msgbtn" href="{{ route('notifications') }}" aria-label="{{ __('Notifications') }}" title="{{ __('Notifications') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons['bell'] !!}</svg><span class="badge" id="bell-badge" @unless ($bell ?? 0) hidden @endunless>{{ $bell ?? 0 }}</span></a>
        <a class="iconbtn msgbtn" href="{{ route('messages') }}" aria-label="Messages{{ ($unread ?? 0) ? ' ('.$unread.' non lus)' : '' }}" title="Messages"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons['msg'] !!}</svg><span class="badge" id="unread-badge" @unless ($unread ?? 0) hidden @endunless>{{ $unread ?? 0 }}</span></a>
        <span class="rolechip">{{ $isRecruiter ? 'Recruteur' : 'Sportif' }}@if ($demo) · démo @endif</span>
      @else
        <a class="btn sm" href="{{ route('phone.login') }}">{{ __('Créer un compte') }}</a>
      @endif
    </div>
  </div>
</header>

@if (session('status'))
  <div class="flash gut" role="status"><span>{{ session('status') }}</span></div>
@endif

<main class="appmain @yield('main-class')">
@yield('content')
</main>

@unless (View::hasSection('fullbleed'))
<footer class="appfoot gut">
  <a href="{{ route('home') }}">{{ __('Accueil') }}</a>
  <a href="{{ route('stories') }}">{{ __('Ils ont été recrutés') }}</a>
  <a href="{{ route('partners') }}">{{ __('Partenaires') }}</a>
  <a href="{{ route('privacy') }}">{{ __('Confidentialité') }}</a>
  <a href="{{ route('terms') }}">{{ __('Conditions') }}</a>
  <a href="{{ route('child-safety') }}">{{ __('Sécurité des enfants') }}</a>
  <a href="{{ route('support') }}">{{ __('Aide') }}</a>
  <a href="{{ route('audio-help') }}">{{ __('Aide audio') }}</a>
  <span class="footcontact"><a href="mailto:{{ config('terrain.email') }}">{{ config('terrain.email') }}</a> · <x-phones /></span>
  @foreach (config('terrain.locales') as $code => $label)<a href="{{ route('locale', $code) }}" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ strtoupper($code) }}</a>@endforeach
  @if ($demo)<span>{{ __('Mode démo : données d\'exemple') }}</span>@endif
</footer>
@endunless

<nav class="tabbar" aria-label="Onglets">
  @foreach ($tabs as [$name, $label, $icon])
    <a href="{{ route($name) }}" @if ($active($name)) aria-current="page" @endif>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons[$icon] !!}</svg>{{ $label }}
    </a>
  @endforeach
</nav>

<script>
  window.Terrain = {
    csrf: document.querySelector('meta[name="csrf-token"]').content,
    loggedIn: {{ $me ? 'true' : 'false' }},
    loginUrl: @json(route('login')),
    post: function (url, body) {
      return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.Terrain.csrf },
        body: JSON.stringify(body || {})
      }).then(function (r) {
        if (r.status === 401) { location.href = window.Terrain.loginUrl; throw new Error('login'); }
        if (!r.ok) throw new Error('http ' + r.status);
        return r.json();
      });
    },
    lite: {{ $lite ? 'true' : 'false' }},
    pushKey: @json(\App\Terrain\Growth\WebPush::configured() ? config('terrain.push.public_key') : null),
    pushUrl: @json($me ? route('push.subscribe') : null),
    toast: function (text) {
      var d = document.createElement('div'); d.className = 'toast'; d.setAttribute('role', 'status'); d.textContent = text;
      document.body.appendChild(d); setTimeout(function () { d.remove(); }, 2200);
    }
  };
</script>
<script>
// Économie de données : coché à la main, ou proposé automatiquement sur une connexion lente (2G/3G, mode « économiseur »)
(function () {
  var t = document.getElementById('lite-toggle');
  function set(on) { location.href = @json(route('lite', '__ON__')).replace('__ON__', on ? '1' : '0'); }
  if (t) t.addEventListener('change', function () { set(t.checked); });
  var c = navigator.connection;
  var asked = false; try { asked = localStorage.getItem('terrain_lite_asked') === '1'; localStorage.setItem('terrain_lite_asked', '1'); } catch (e) {}
  if (!window.Terrain.lite && !asked && c && (c.saveData || /2g|3g/.test(c.effectiveType || ''))) {
    var d = document.createElement('div'); d.className = 'toast'; d.style.pointerEvents = 'auto';
    d.innerHTML = 'Connexion lente détectée. <button type="button" class="linkbtn" style="color:#fff;text-decoration:underline">Activer l\'économie de données</button>';
    d.querySelector('button').onclick = function () { set(true); };
    document.body.appendChild(d); setTimeout(function () { d.remove(); }, 9000);
  }
})();
</script>
@if ($me && ! request()->routeIs('calls.show'))
<div class="ringing" id="ringing" hidden role="alertdialog" aria-label="Appel entrant">
  <div class="ringcard">
    <div class="ringpulse"></div>
    <b id="ring-name"></b><span id="ring-kind" class="meta"></span>
    <div class="row" style="gap:14px;justify-content:center;margin-top:14px">
      <button class="cbtn red" type="button" id="ring-decline">Refuser</button>
      <a class="cbtn green" id="ring-accept" href="#">Répondre</a>
    </div>
  </div>
</div>
<script>
// Appel entrant et messages non lus, vérifiés toutes les 4 s sur toutes les pages
(function () {
  var box = document.getElementById('ringing'), badge = document.getElementById('unread-badge'), current = null;
  var incomingUrl = @json(route('calls.incoming')), statusUrl = @json(route('calls.status', '__ID__'));
  var lastNotif = -1, ringTimer = null, audio = null;
  // Petits sons sans fichier : « ding » pour un message, sonnerie pour un appel (après un premier toucher sur la page)
  function beep(freqs, len) {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      freqs.forEach(function (f, i) {
        var o = audio.createOscillator(), g = audio.createGain(), t = audio.currentTime + i * len;
        o.frequency.value = f; o.type = 'sine'; g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.25, t + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t + len); o.connect(g); g.connect(audio.destination); o.start(t); o.stop(t + len);
      });
    } catch (e) {}
  }
  function ring(on) {
    clearInterval(ringTimer); ringTimer = null;
    if (on) { beep([660, 880], 0.35); ringTimer = setInterval(function () { beep([660, 880], 0.35); }, 2200); }
  }
  function alertBox(n) {
    var a = document.createElement('a'); a.className = 'ntoast'; a.href = n.url || @json(route('notifications')); a.setAttribute('role', 'alert');
    var ico = { message: '💬', comment: '💭', offer: '📩', call_missed: '📞', payment: '✅', star: 'star' }[n.type] || 'bell';
    a.innerHTML = '<i></i><span><b></b><small></small></span>';
    a.querySelector('i').textContent = ico; a.querySelector('b').textContent = n.title; a.querySelector('small').textContent = n.body || '';
    document.body.appendChild(a); setTimeout(function () { a.classList.add('out'); setTimeout(function () { a.remove(); }, 400); }, 6000);
    beep([880, 1320], 0.12);
    if (navigator.vibrate) navigator.vibrate(80);
  }
  function check() {
    if (document.hidden) return;
    fetch(incomingUrl + '?apres=' + lastNotif, { headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d) return;
      // nouvelle notification depuis la dernière vérification (pas pour l'appel : il a sa propre fenêtre)
      if (d.notif && d.notif.type !== 'call' && !(d.notif.type === 'message' && location.pathname.indexOf(new URL(d.notif.url || '/', location.href).pathname) === 0)) alertBox(d.notif);
      if (d.notif_max != null) lastNotif = Math.max(lastNotif, d.notif_max);
      if (badge) { badge.hidden = !d.unread; badge.textContent = d.unread; }
      var bell = document.getElementById('bell-badge'); if (bell && d.bell != null) { bell.hidden = !d.bell; bell.textContent = d.bell; }
      document.dispatchEvent(new CustomEvent('terrain:unread', { detail: d.unread }));
      if (d.call && (!current || current !== d.call.id)) {
        current = d.call.id;
        document.getElementById('ring-name').textContent = d.call.partner.name;
        document.getElementById('ring-kind').textContent = d.call.kind === 'video' ? 'Appel vidéo entrant…' : 'Appel vocal entrant…';
        document.getElementById('ring-accept').href = d.url; box.hidden = false;
        if (navigator.vibrate) navigator.vibrate([400, 200, 400]);
        ring(true);
      } else if (!d.call && current) { current = null; box.hidden = true; ring(false); }
    }).catch(function () {});
  }
  document.getElementById('ring-decline').onclick = function () {
    if (current) Terrain.post(statusUrl.replace('__ID__', current), { status: 'declined' }).catch(function () {});
    box.hidden = true; ring(false);
  };
  document.getElementById('ring-accept').addEventListener('click', function () { ring(false); });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
  setInterval(check, 4000); setTimeout(check, 800);
})();
</script>
<div class="pushbar" id="pushbar" hidden>
  <span>🔔 {{ __('Sois prévenu des messages et des appels, même quand le site est fermé.') }}</span>
  <button class="btn sm" type="button" id="push-on">{{ __('Activer') }}</button>
  <button class="linkbtn" type="button" id="push-later" aria-label="{{ __('Plus tard') }}">{{ __('Plus tard') }}</button>
</div>
<script>
// Notifications sur le téléphone (Web Push) : enregistrement du service worker et abonnement de l'appareil
(function () {
  var T = window.Terrain;
  if (!('serviceWorker' in navigator)) return;
  navigator.serviceWorker.register(@json(asset('sw.js'))).catch(function () {});
  if (!T.pushKey || !('PushManager' in window) || !('Notification' in window)) return;
  function key(b64) {
    var pad = '='.repeat((4 - b64.length % 4) % 4), raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length); for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i); return out;
  }
  T.pushSubscribe = function () {
    return Notification.requestPermission().then(function (p) {
      if (p !== 'granted') throw new Error('refus');
      return navigator.serviceWorker.ready;
    }).then(function (reg) {
      return reg.pushManager.getSubscription().then(function (sub) {
        return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key(T.pushKey) });
      });
    }).then(function (sub) { return T.post(T.pushUrl, sub.toJSON()); });
  };
  var bar = document.getElementById('pushbar'), later = false;
  try { later = localStorage.getItem('terrain_push_later') > Date.now() - 7 * 864e5; } catch (e) {}
  if (Notification.permission === 'granted') { T.pushSubscribe().catch(function () {}); }   // garde l'abonnement à jour
  else if (Notification.permission === 'default' && !later) { setTimeout(function () { bar.hidden = false; }, 2500); }
  document.getElementById('push-on').onclick = function () {
    bar.hidden = true;
    T.pushSubscribe().then(function () { T.toast(@json(__('Notifications activées'))); }, function () { T.toast(@json(__('Notifications refusées : tu peux les activer dans les réglages du navigateur.'))); });
  };
  document.getElementById('push-later').onclick = function () { bar.hidden = true; try { localStorage.setItem('terrain_push_later', Date.now()); } catch (e) {} };
})();
</script>
@endif
@stack('scripts')
</body>
</html>
