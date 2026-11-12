@extends('layouts.web')

@section('title', __('Explorer').' – '.config('terrain.app_name'))
@section('description', __('Joueurs, entraîneurs, arbitres, clubs, détections, matchs en direct, défis et vidéos de foot : tout le football d’Afrique de l’Ouest sur FootPro.'))

@php
  $tiles = [
      ['rankings', 'star', __('Meilleurs joueurs'), __('Le classement de l’algorithme, par poste et par ville')],
      ['actors', 'whistle', __('Acteurs du foot'), __('Entraîneurs, arbitres, médecins, kinés, préparateurs…')],
      ['clubs', 'stadium', __('Clubs et académies'), __('Leur présentation, leurs détections, leurs joueurs')],
      ['events', 'calendar', __('Détections et essais'), __('Les journées de détection près de chez toi')],
      ['matches', 'score', __('Matchs et scores'), __('Hier, aujourd’hui, demain : scores en direct')],
      ['live', 'live', __('En direct'), __('Matchs filmés et lives de supporters')],
      ['challenges', 'trophy', __('Défis'), __('Publie ta plus belle action, vote pour les autres')],
      ['posts', 'news', __('Actu'), __('Publications texte et photo des membres')],
      ['coaching', 'coach', __('Coaching'), __('Ton programme d’entraînement gratuit, ou un coach')],
      ['discover', 'play', __('Découvrir'), $discover ? __(':n vidéos de foot pour apprendre et t’inspirer', ['n' => number_format($discover, 0, ',', ' ')]) : __('Vidéos de foot pour apprendre et t’inspirer')],
      ['stories', 'party', __('Ils ont été recrutés'), __('Des joueurs repérés grâce à FootPro')],
  ];
  if ($me) {
      array_splice($tiles, 4, 0, [['offers', 'tag', __('Offres et essais'), __('Les annonces des clubs et des recruteurs')]]);
  }
@endphp

@section('content')
<div class="page gut">
  <h1>{{ __('Explorer') }}</h1>

  <form method="GET" action="{{ route('explore') }}" class="exsearch" role="search">
    <label class="sr-only" for="exq">{{ __('Chercher un joueur') }}</label>
    <input id="exq" name="q" value="{{ $q }}" placeholder="{{ __('Chercher un joueur : nom, club, ville…') }}" autocomplete="off">
    <button class="btn" type="submit">{{ __('Chercher') }}</button>
  </form>
  <nav class="chips pickchips exchips" aria-label="{{ __('Poste') }}">
    @foreach ($postes as $p)<a class="chip" href="{{ route('explore', ['poste' => $p] + ($q !== '' ? ['q' => $q] : [])) }}" aria-current="{{ $poste === $p ? 'true' : 'false' }}">{{ __($p) }}</a>@endforeach
  </nav>

  @if ($searched)
    <section class="exresults">
      <h2>{{ count($players) }} {{ count($players) > 1 ? __('joueurs') : __('joueur') }}</h2>
      @forelse ($players as $a)
        <a class="listrow" href="{{ route('athletes.show', $a['id']) }}">
          <span style="display:flex;gap:12px;align-items:center;min-width:0"><x-av :name="$a['name']" :sport="$a['sport']" />
            <span style="min-width:0"><b>{{ $a['name'] }}</b>@if ($a['verified']) <x-ver />@endif<small>{{ __($a['fields']['Poste'] ?? '') }} · {{ $a['age'] }} {{ __('ans') }} · {{ $a['club'] }} · {{ $a['city'] }}</small></span></span>
          <span class="btn sm ghost">{{ __('Voir') }}</span>
        </a>
      @empty
        <p class="meta">{{ __('Aucun joueur trouvé. Essaie un autre nom ou un autre poste.') }}</p>
      @endforelse
      @if (($me['role'] ?? null) === 'recruiter')<p><a href="{{ route('search') }}">{{ __('Recherche avancée (âge, pays, pied fort, disponibilité…)') }} →</a></p>@endif
    </section>
  @endif

  <div class="extiles">
    @foreach ($tiles as [$r, $ico, $title, $sub])
      <a class="extile" href="{{ route($r) }}"><x-ico :name="$ico" /><span><b>{{ $title }}</b><small>{{ $sub }}</small></span></a>
    @endforeach
  </div>

  @if (count($actors))
    <h2 style="margin-top:24px">{{ __('Acteurs du foot') }}</h2>
    <div class="exactors">
      @foreach ($actors as $a)
        <a class="exactor" href="{{ route('athletes.show', $a['id']) }}"><x-av :name="$a['name']" :sport="$a['sport']" /><b>{{ $a['name'] }}</b><small>{{ __($a['profession'] ?? '') }}</small></a>
      @endforeach
    </div>
  @endif
</div>
@endsection
