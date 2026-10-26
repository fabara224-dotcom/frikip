@extends('layouts.app')

@section('title', config('terrain.app_name'))

@push('meta')
<meta property="og:title" content="{{ config('terrain.app_name') }} – {{ __('Ton talent mérite d’être vu') }}">
<meta property="og:description" content="{{ __('Publie tes compilations, les clubs te trouvent.') }}">
@endpush

@section('content')
<section class="hero">
    <div class="wrap gut">
      <div>
        <span class="eyebrow">Recrutement sportif · Afrique de l'Ouest</span>
        <h1>Ton talent mérite <em>d'être vu.</em></h1>
        <p>Publie tes compilations en vidéo verticale. Les clubs, agents, académies et universités te trouvent, regardent tes actions et te contactent directement dans {{ config('terrain.app_name', 'Frikip') }}.</p>
        <div class="row" style="margin-bottom:14px">
          <a class="btn" href="{{ route('feed') }}">Voir les compilations</a>
          <a class="btn ghost" href="{{ route('matches') }}" style="background:rgba(255,255,255,.12);color:#fff">Scores en direct</a>
          <a class="btn ghost" href="{{ route('phone.login') }}" style="background:rgba(255,255,255,.12);color:#fff">{{ __('Créer un compte en 30 s') }}</a>
        </div>
        <div class="stores">
          <a class="store" href="#bientot"><small>Bientôt sur</small><b>Google Play</b></a>
          <a class="store" href="#bientot"><small>Bientôt sur</small><b>App Store</b></a>
        </div>
      </div>
      <div class="mock" aria-label="Aperçu du fil vidéo de l'application" role="img">
        <div class="v"></div>
        <svg viewBox="0 0 200 400" preserveAspectRatio="none" fill="none" stroke="#fff" stroke-width="1.5"><rect x="14" y="20" width="172" height="360"/><path d="M14 200h172"/><circle cx="100" cy="200" r="30"/><rect x="55" y="20" width="90" height="50"/><rect x="55" y="330" width="90" height="50"/></svg>
        <div class="cap">{{ __('Ta compilation ici') }}</div>
        <div class="rail"><span><i></i></span><span><i class="h"></i>J’aime</span><span><i></i>{{ __('Avis') }}</span><span><i></i>Partager</span></div>
        <div class="meta2"><b>{{ __('Ton nom') }}</b> · {{ __('Ton poste') }}<br>{{ __('Ton club') }} · {{ __('Ta ville') }}</div>
        <div class="bar"><i></i></div>
      </div>
    </div>
  </section>

  <section class="band counters">
    <div class="wrap gut">
      <div class="grid3 counter">
        <div><b data-count="{{ $counters['players'] }}">{{ number_format($counters['players'], 0, ',', ' ') }}</b><span>{{ __('sportifs') }}</span></div>
        <div><b data-count="{{ $counters['clubs'] }}">{{ number_format($counters['clubs'], 0, ',', ' ') }}</b><span>{{ __('clubs, agents et académies') }}</span></div>
        <div><b data-count="{{ $counters['trials'] }}">{{ number_format($counters['trials'], 0, ',', ' ') }}</b><span>{{ __('candidatures et inscriptions aux essais') }}</span></div>
      </div>
    </div>
  </section>

  <section class="band">
    <div class="wrap gut">
      <div class="grid2">
        @if ($winner)
          <a class="card winner" href="{{ route('athletes.show', $winner['athlete']['id']) }}">
            <span class="eyebrow">🏆 {{ __('Joueur de la semaine') }}</span>
            <b style="font-size:26px">{{ $winner['athlete']['name'] }}</b>
            <span class="meta">{{ $winner['title'] }} · {{ $winner['votes'] }} {{ __('votes') }} · {{ $winner['athlete']['sport'] }}, {{ $winner['athlete']['city'] }}</span>
            <span class="btn sm" style="align-self:flex-start">{{ __('Voir ses vidéos') }}</span>
          </a>
        @endif
        <a class="card" href="{{ route('challenges') }}">
          <span class="eyebrow">{{ __('Défi de la semaine') }}</span>
          <b style="font-size:22px">{{ __('Publie ta meilleure action, la communauté vote') }}</b>
          <span class="meta">{{ __('Le gagnant est mis en avant sur l’accueil et dans le fil.') }}</span>
          <span class="btn ghost sm" style="align-self:flex-start">{{ __('Voir le défi') }}</span>
        </a>
      </div>
      @if (count($events))
        <div class="section-label" style="margin-top:22px">{{ __('Prochaines détections') }}</div>
        <div class="grid3">
          @foreach ($events as $e)
            <a class="card" href="{{ route('events.show', $e->id) }}"><b>{{ $e->title }}</b><span class="meta">{{ \Illuminate\Support\Carbon::parse($e->date)->translatedFormat('l j F') }} · {{ $e->city }}</span></a>
          @endforeach
        </div>
      @endif
      <div class="row" style="gap:8px;flex-wrap:wrap;margin-top:16px">
        <a class="btn ghost sm" href="{{ route('rankings') }}">⭐ {{ __('Classements') }}</a>
        <a class="btn ghost sm" href="{{ route('stories') }}">🎉 {{ __('Ils ont été recrutés') }}</a>
        <a class="btn ghost sm" href="{{ route('clubs') }}">🏟️ {{ __('Clubs et académies') }}</a>
        <a class="btn ghost sm" href="{{ route('partners') }}">🤝 {{ __('Devenir partenaire') }}</a>
      </div>
    </div>
  </section>

  <section class="band">
    <div class="wrap gut">
      <span class="eyebrow">Comment ça marche</span>
      <h2>Un profil, des vidéos, des recruteurs</h2>
      <div class="grid3">
        <div class="card"><h3>Pour les sportifs</h3>
          <ul><li>Profil de sportif : poste, taille, records, palmarès</li><li>Compilations en vidéo plein écran</li><li>Statistiques : qui a vu ton profil</li><li>Candidature aux détections en deux gestes</li></ul></div>
        <div class="card"><h3>Pour les recruteurs</h3>
          <ul><li>Recherche par sport, poste, âge et pays</li><li>Comparaison de deux joueurs</li><li>Publication d'offres et de détections</li><li>Messagerie directe avec les talents</li></ul></div>
        <div class="card"><h3>Scores en direct</h3>
          <ul><li>Championnats jeunes et locaux</li><li>Classements mis à jour pendant les matchs</li><li>Notes des joueurs reliées à leur profil</li><li>Vote du joueur du match</li></ul></div>
      </div>
      <div class="sports" aria-label="Sports couverts"><span>Football</span><span>Basket</span><span>Athlétisme</span><span>Lutte</span><span>Judo</span><span>Handball</span><span>Tennis</span><span>Natation</span></div>
    </div>
  </section>

  <section class="band safe">
    <div class="wrap gut">
      <span class="eyebrow">Confiance et sécurité</span>
      <h2>Protéger les jeunes talents</h2>
      <div class="grid2">
        <div class="card"><h3>Recruteurs vérifiés</h3><p>Seuls les clubs et agents dont la licence a été vérifiée peuvent écrire aux sportifs. Les comptes des moins de 18 ans sont validés par un parent ou un tuteur.</p></div>
        <div class="card"><h3>Jamais d'argent pour un essai</h3><p>Un message qui parle de frais, de virement ou de visa déclenche une alerte. Chaque profil et chaque message peut être signalé en un geste.</p></div>
        <div class="card"><h3>Tes données t'appartiennent</h3><p>Tu peux consulter, corriger ou supprimer ton compte à tout moment, depuis l'application ou depuis ce site. <a href="{{ route('privacy') }}">Lire la politique de confidentialité</a>.</p></div>
        <div class="card"><h3>Besoin d'aide ?</h3><p>Une question, un problème, un comportement suspect : <a href="{{ route('support') }}">contacte l'équipe</a> ou consulte nos <a href="{{ route('child-safety') }}">normes de sécurité des enfants</a>.</p></div>
      </div>
    </div>
  </section>

  <section class="band" id="bientot">
    <div class="wrap gut narrow" style="text-align:center">
      <h2>Bientôt disponible</h2>
      <p class="lead"><x-cfg key="app_name" /> arrive d'abord sur Android, puis sur iPhone. Écris-nous pour être prévenu du lancement : <x-cfg key="email" mailto /></p>
      <div class="row" style="justify-content:center"><a class="btn ghost" href="{{ route('deletion') }}">Supprimer mon compte</a><a class="btn ghost" href="{{ route('privacy') }}">Confidentialité</a></div>
    </div>
  </section>
@endsection
