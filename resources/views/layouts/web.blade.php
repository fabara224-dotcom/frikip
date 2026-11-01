{{-- Mise en page de l'application web : barre du haut, onglets en bas sur téléphone. --}}
@php
    $app = config('terrain.app_name');
    $isRecruiter = ($me['role'] ?? null) === 'recruiter';
    $more = [
        ['challenges', __('Défis'), 'trophy'], ['coaching', __('Coaching'), 'coach'],
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
    $current = request()->route()?->getName();
    $active = fn ($name) => $current === $name || ($name === 'matches' && str_starts_with((string) $current, 'matches')) || ($name === 'search' && $current === 'compare')
        || ($name === 'challenges' && str_starts_with((string) $current, 'challenges')) || ($name === 'coaching' && str_starts_with((string) $current, 'coaching'))
        || ($name === 'live' && str_starts_with((string) $current, 'live')) || ($name === 'clubs' && str_starts_with((string) $current, 'clubs'))
        || ($name === 'events' && str_starts_with((string) $current, 'events')) || ($name === 'posts' && str_starts_with((string) $current, 'posts'));
    // Menu latéral (ordinateur) : les rubriques principales, comme dans une appli de réseau social
    $side = [['feed', __('Accueil'), 'home'], ['posts', __('Actu'), 'news'], ['explore', __('Explorer'), 'compass'], ['matches', __('Matchs'), 'score'], ['live', __('En direct'), 'live'],
        ['challenges', __('Défis'), 'trophy'], ['rankings', __('Joueurs'), 'star'], ['clubs', __('Clubs'), 'stadium'], ['events', __('Détections'), 'calendar'],
        ['coaching', __('Coaching'), 'coach']];
    if ($me) {
        $side[] = ['offers', $isRecruiter ? __('Mes offres') : __('Offres'), 'tag'];
        $side[] = ['messages', __('Messages'), 'msg'];
    }
    $side[] = ['stories', __('Ils ont été recrutés'), 'party'];
    // Barre du bas (téléphone) : Accueil, Explorer, +, Matchs, Profil
    $plusUrl = $me ? ($isRecruiter ? route('offers') : route('publish')) : route('phone.login');
    $plusLabel = $me ? ($isRecruiter ? __('Publier une offre') : __('Publier')) : __('Créer un compte');
    $profileRoute = $me ? 'me' : 'login';
    $initial = $me ? collect(preg_split('/\s+/', trim($me['name'] ?? '?')))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') : '';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', $app)</title>
@stack('meta')
<meta name="description" content="@yield('description', $app.' : le réseau social du foot. Vidéos, actu des joueurs et des clubs, recrutement et scores en direct.')">
<link rel="preload" href="{{ asset('fonts/inter-var.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('fonts/barlow-condensed-800-italic.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('css/terrain.css') }}">
<link rel="stylesheet" href="{{ asset('css/web.css') }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#07100B">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('icons/favicon-32.png') }}" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="stylesheet" href="{{ asset('css/frikip.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/frikip-plus.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('css/frikip-pro.css') }}?v=1">
</head>
<body class="webapp @yield('body-class') {{ $lite ? 'lite' : '' }}">
<header class="apptop">
  <div class="apptop-in gut">
    <a class="logo" href="{{ route('feed') }}" aria-label="{{ $app }}"><x-logo /></a>
    <form class="topsearch" method="GET" action="{{ route('search') }}" role="search">
      <x-ico name="search" />
      <input type="search" name="q" placeholder="{{ __('Rechercher un joueur, un club, un match…') }}" aria-label="{{ __('Rechercher') }}" value="{{ request('q') }}">
    </form>
    <nav class="topicons" aria-label="{{ __('Raccourcis') }}">
      <a href="{{ route('feed') }}" @if ($active('feed')) aria-current="page" @endif><x-ico name="home" /><span>{{ __('Accueil') }}</span></a>
      @if ($me)
        <a class="msgbtn" href="{{ route('messages') }}" @if ($active('messages')) aria-current="page" @endif><x-ico name="msg" /><span>{{ __('Messages') }}</span><i class="badge" id="unread-badge" @unless ($unread ?? 0) hidden @endunless>{{ $unread ?? 0 }}</i></a>
        <a class="msgbtn" href="{{ route('notifications') }}" @if ($active('notifications')) aria-current="page" @endif><x-ico name="bell" /><span>{{ __('Notifications') }}</span><i class="badge" id="bell-badge" @unless ($bell ?? 0) hidden @endunless>{{ $bell ?? 0 }}</i></a>
      @else
        <a href="{{ route('matches') }}" @if ($active('matches')) aria-current="page" @endif><x-ico name="score" /><span>{{ __('Matchs') }}</span></a>
      @endif
    </nav>
    <div class="apptop-actions">
      <a class="iconbtn topsearch-sm" href="{{ route('explore') }}" aria-label="{{ __('Rechercher') }}"><x-ico name="search" /></a>
      @if ($me)
        <a class="iconbtn msgbtn bell-sm" href="{{ route('notifications') }}" aria-label="{{ __('Notifications') }}"><x-ico name="bell" /><span class="badge" @unless ($bell ?? 0) hidden @endunless>{{ $bell ?? 0 }}</span></a>
      @endif
      <details class="moremenu">
        <summary class="@if ($me) avatarbtn @else iconbtn @endif" aria-label="{{ __('Menu') }}" title="{{ __('Menu') }}">
          @if ($me)<span class="av sm">{{ $initial }}</span><svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          @else<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>@endif
        </summary>
        <nav class="card" aria-label="{{ __('Menu') }}">
          @if ($me)
            <a class="menuhead" href="{{ route('me') }}"><span class="av">{{ $initial }}</span><span><b>{{ $me['name'] ?? '' }}</b><small>{{ $isRecruiter ? __('Recruteur') : __('Sportif') }}@if ($demo) · démo @endif · {{ __('Voir mon profil') }} ›</small></span></a>
          @endif
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
          @if ($me)
            <form method="POST" action="{{ route('logout') }}" class="menulogout">@csrf<button type="submit"><x-ico name="logout" />{{ __('Se déconnecter') }}</button></form>
          @endif
        </nav>
      </details>
      @unless ($me)
        <a class="btn sm" href="{{ route('phone.login') }}">{{ __('Créer un compte') }}</a>
      @endunless
    </div>
  </div>
</header>

<div class="shell">
<aside class="sidenav" aria-label="{{ __('Navigation') }}">
  <nav>
    @foreach ($side as [$name, $label, $icon])
      <a href="{{ route($name) }}" @if ($active($name)) aria-current="page" @endif><x-ico :name="$icon" />{{ $label }}</a>
    @endforeach
  </nav>
  <a class="btn sidecta" href="{{ $plusUrl }}"><x-ico name="plus" />{{ $plusLabel }}</a>
  @unless ($me)
    <div class="sidecard">
      <b>{{ __('Ton talent mérite d’être vu.') }}</b>
      <span>{{ __('Publie tes compilations : les clubs, agents et académies te trouvent.') }}</span>
      <a class="btn ghost sm" href="{{ route('login') }}">{{ __('Se connecter') }}</a>
    </div>
  @endunless
  <div class="sidefoot">
    <a href="{{ route('privacy') }}">{{ __('Confidentialité') }}</a> · <a href="{{ route('terms') }}">{{ __('Conditions') }}</a> · <a href="{{ route('support') }}">{{ __('Aide') }}</a>
    <span>© {{ date('Y') }} {{ $app }}</span>
  </div>
</aside>

<div class="shell-main">
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
</div>

@hasSection('rail')
<aside class="siderail" aria-label="{{ __('À découvrir') }}">
@yield('rail')
</aside>
@endif
</div>

<nav class="tabbar" aria-label="Onglets">
  <a href="{{ route('feed') }}" @if ($active('feed') || $active('posts')) aria-current="page" @endif><x-ico name="home" />{{ __('Accueil') }}</a>
  <a href="{{ route('explore') }}" @if ($active('explore') || $active('search')) aria-current="page" @endif><x-ico name="compass" />{{ __('Explorer') }}</a>
  <a class="tabplus" href="{{ $plusUrl }}" aria-label="{{ $plusLabel }}"><span><x-ico name="plus" /></span></a>
  <a href="{{ route('matches') }}" @if ($active('matches')) aria-current="page" @endif><x-ico name="score" />{{ __('Matchs') }}</a>
  <a href="{{ route($profileRoute) }}" @if ($active($profileRoute)) aria-current="page" @endif><x-ico name="user" />{{ $me ? __('Profil') : __('Connexion') }}</a>
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
      document.querySelectorAll('#bell-badge, .bell-sm .badge').forEach(function (bell) { if (d.bell != null) { bell.hidden = !d.bell; bell.textContent = d.bell; } });
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
