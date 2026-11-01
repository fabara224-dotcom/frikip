@php
    $app = config('terrain.app_name');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', $app)</title>
@stack('meta')
<meta name="description" content="@yield('description', $app.' : les sportifs publient leurs compilations vidéo, les clubs et agents les recrutent.')">
@hasSection('noindex')<meta name="robots" content="noindex, nofollow">@endif
<link rel="preload" href="{{ asset('fonts/inter-var.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('fonts/barlow-condensed-800-italic.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('css/terrain.css') }}">
<link rel="stylesheet" href="{{ asset('css/frikip.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/frikip-pro.css') }}?v=1">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<meta name="theme-color" content="#07100B">
</head>
<body>
@php
    $links = [
        'privacy' => 'Confidentialité',
        'terms' => 'Conditions',
        'child-safety' => 'Sécurité des enfants',
        'deletion' => 'Supprimer mon compte',
        'support' => 'Aide',
    ];
@endphp
<header class="top">
  <div class="wrap gut">
    <a class="logo" href="{{ route('home') }}" aria-label="{{ $app }}"><x-logo /></a>
    <a class="btn" href="{{ route('feed') }}" style="padding:8px 14px;font-size:14px">Ouvrir l'app web</a>
    <nav class="nav" aria-label="Pages">
      @foreach ($links as $name => $label)
        <a href="{{ route($name) }}" @if (request()->routeIs($name)) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>
  </div>
</header>

<main>
@if (session('status'))
  <div class="wrap gut" style="padding-top:14px"><div class="msg ok" role="status">{{ session('status') }}</div></div>
@endif
@yield('content')
</main>

<footer class="foot">
  <div class="wrap gut">
    <div>
      <b>{{ $app }}</b><br>
      Édité par <x-cfg key="editor" /> · <x-cfg key="country" /><br>
      Contact : <x-cfg key="email" mailto /><br>
      Tél. : <x-phones /><br>
      © {{ date('Y') }} {{ config('terrain.editor') ?: $app }}
    </div>
    <nav aria-label="Informations légales">
      <a href="{{ route('home') }}">Accueil</a>
      @foreach ($links as $name => $label)
        <a href="{{ route($name) }}">{{ $label }}</a>
      @endforeach
    </nav>
  </div>
</footer>
<script>
  // Boutons « Copier »
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]'); if (!b) return;
    var t = document.querySelector(b.getAttribute('data-copy')); if (!t) return;
    var done = function () { b.textContent = 'Copié'; setTimeout(function () { b.textContent = 'Copier'; }, 1500); };
    var select = function () { var r = document.createRange(); r.selectNodeContents(t); var s = getSelection(); s.removeAllRanges(); s.addRange(r); };
    if (navigator.clipboard) navigator.clipboard.writeText(t.textContent.trim()).then(done, select); else select();
  });
</script>
</body>
</html>
