@php
    $app = config('terrain.app_name');
    $count = function (string $table, callable $where) {
        try { $q = \Illuminate\Support\Facades\DB::table($table); $where($q); return (int) $q->count(); } catch (\Throwable) { return 0; }
    };
    $todo = $count('moderation_events', fn ($q) => $q->where('status', 'a_traiter')) + $count('reports', fn ($q) => $q->where('status', 'nouveau'));
    $sections = [
        'Supervision' => [
            ['admin.index', 'Vue d’ensemble', 'stats', null],
            ['admin.members', 'Membres', 'search', null],
            ['admin.moderation', 'Modération', 'check', $todo],
            ['admin.algorithm', 'Algorithme', 'star', null],
        ],
        'Contenus' => [
            ['admin.content', 'Vidéos, offres…', 'film', null],
            ['admin.actu', 'Actu et stories', 'news', \App\Http\Controllers\AdminActuController::todo()],
            ['admin.live', 'Direct', 'live', $count('live_streams', fn ($q) => $q->where('approved', false))],
            ['admin.broadcasters', 'Diffuseurs', 'tv', null],
            ['admin.ads', 'Publicités', 'ad', null],
            ['admin.challenges', 'Défis', 'trophy', null],
            ['admin.coaching', 'Coaching', 'coach', $count('coaching_requests', fn ($q) => $q->where('status', 'payee'))],
        ],
        'Activité' => [
            ['admin.payments', 'Paiements', 'receipt', null],
            ['admin.montages', 'Montages', 'film', $count('video_orders', fn ($q) => $q->whereIn('status', ['payee', 'en_cours']))],
            ['admin.verifications', 'Vérifications', 'card', $count('verification_requests', fn ($q) => $q->where('status', 'en_attente'))],
            ['admin.clubs', 'Clubs', 'stadium', null],
            ['admin.stories', 'Témoignages', 'party', null],
            ['admin.partners', 'Partenaires', 'handshake', $count('partnership_requests', fn ($q) => $q->where('status', 'nouvelle'))],
            ['admin.reports', 'Signalements', 'megaphone', null],
            ['admin.deletions', 'Suppressions', 'doc', $count('deletion_requests', fn ($q) => $q->where('status', 'nouvelle'))],
        ],
        'Traçabilité' => [
            ['admin.journal', 'Journal', 'pencil', null],
            ['admin.admins', 'Comptes admin', 'check', null],
            ['admin.account', 'Mon compte', 'card', null],
        ],
    ];
@endphp
<!doctype html>
<html lang="fr" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', 'Admin – '.$app)</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preload" href="{{ asset('fonts/archivo-var.woff') }}" as="font" type="font/woff" crossorigin>
<link rel="stylesheet" href="{{ asset('css/terrain.css') }}">
<link rel="stylesheet" href="{{ asset('css/frikip.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/frikip-plus.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v=1">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<meta name="theme-color" content="#0B2318">
</head>
<body class="adminbody">
<div class="adm">
  <aside class="adm-side" id="adm-side">
    <a class="adm-logo" href="{{ route('admin.index') }}" aria-label="{{ $app }} – espace admin"><img src="{{ asset('brand/logo-blanc.svg') }}" alt="{{ $app }}" width="104" height="28"><span>Admin</span></a>
    <nav aria-label="Espace admin">
      @foreach ($sections as $title => $items)
        <div class="adm-group">
          <span class="adm-gtitle">{{ $title }}</span>
          @foreach ($items as [$route, $label, $ico, $badge])
            @continue(! \App\Terrain\Growth\AdminAuth::allows($admin ?? null, $route))
            <a href="{{ route($route) }}" @if (request()->routeIs($route) || ($route === 'admin.members' && request()->routeIs('admin.member'))) aria-current="page" @endif>
              <x-ico :name="$ico" /><span>{{ $label }}</span>@if ($badge)<b class="adm-badge">{{ $badge }}</b>@endif
            </a>
          @endforeach
        </div>
      @endforeach
    </nav>
    <div class="adm-foot">
      @isset($admin)<a class="adm-me" href="{{ route('admin.account') }}"><b>{{ $admin->name }}</b><small>{{ \App\Terrain\Growth\AdminAuth::ROLES[$admin->role] ?? $admin->role }}</small></a>@endisset
      <a href="{{ route('feed') }}" target="_blank" rel="noopener">Voir le site</a>
      <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit">Se déconnecter</button></form>
    </div>
  </aside>
  <div class="adm-main">
    <header class="adm-top">
      <button class="adm-menu" type="button" aria-controls="adm-side" aria-expanded="false" onclick="var s=document.getElementById('adm-side');var o=s.classList.toggle('open');this.setAttribute('aria-expanded',o)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>Menu</span></button>
      <img class="adm-top-logo" src="{{ asset('brand/logo.svg') }}" alt="{{ $app }}" width="96" height="26">
    </header>
    <main class="adm-content">
      @yield('content')
    </main>
  </div>
</div>
@stack('scripts')
</body>
</html>
