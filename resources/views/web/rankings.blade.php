@extends('layouts.web')

@section('title', __('Meilleurs sportifs').' – '.config('terrain.app_name'))
@section('description', __('Le classement des meilleurs sportifs de Guinée et d’Afrique de l’Ouest, par sport, poste, âge et ville.'))

@section('content')
<div class="page gut">
  <h1>{{ __('Meilleurs sportifs') }}</h1>
  <p class="meta">{{ __('Top') }} {{ $f['poste'] ? mb_strtolower($f['poste']).'s' : ($f['sport'] === 'Tous' ? __('sportifs') : __('joueurs')) }} {{ $f['sport'] !== 'Tous' ? $f['sport'] : '' }} {{ $f['cat'] !== 'Tous' ? $f['cat'] : '' }} {{ $f['city'] ? __('de').' '.$f['city'] : ($f['country'] !== 'Tous' ? '– '.$f['country'] : '') }}.
    {{ __('Note sur 100 calculée par l’algorithme : vues, « j’aime », partages, abonnés, régularité, défis gagnés, profil complet et vérifié.') }}</p>

  <form method="GET" action="{{ route('rankings') }}" class="filterbar">
    @unless (\App\Terrain\SportFields::single())    <select name="sport" aria-label="{{ __('Sport') }}" onchange="this.form.submit()"><option value="Tous">{{ __('Tous les sports') }}</option>@foreach ($sports as $s)<option @selected($f['sport'] === $s)>{{ $s }}</option>@endforeach</select>@endunless
    <select name="country" aria-label="{{ __('Pays') }}" onchange="this.form.submit()"><option value="Tous">{{ __('Tous pays') }}</option>@foreach ($countries as $c)<option @selected($f['country'] === $c)>{{ $c }}</option>@endforeach</select>
    <select name="city" aria-label="{{ __('Ville') }}" onchange="this.form.submit()"><option value="">{{ __('Toutes villes') }}</option>@foreach ($cities as $c)<option @selected($f['city'] === $c)>{{ $c }}</option>@endforeach</select>
    @if ($postes)<select name="poste" aria-label="{{ __('Poste') }}" onchange="this.form.submit()"><option value="">{{ __('Tous postes') }}</option>@foreach ($postes as $p)<option @selected($f['poste'] === $p)>{{ $p }}</option>@endforeach</select>@endif
    <select name="cat" aria-label="{{ __('Catégorie') }}" onchange="this.form.submit()">@foreach (['Tous' => __('Toutes catégories'), 'U17' => 'U17', 'U20' => 'U20', 'Seniors' => 'Seniors'] as $k => $l)<option value="{{ $k }}" @selected($f['cat'] === $k)>{{ $l }}</option>@endforeach</select>
    <noscript><button class="btn sm">OK</button></noscript>
  </form>

  <ol class="rank">
    @forelse ($list as $i => $a)
      <li class="{{ $i < 3 ? 'podium p'.($i + 1) : '' }}">
        <span class="pos">{{ $i + 1 }}</span>
        <x-av :name="$a['name']" :sport="$a['sport']" />
        <a class="grow" href="{{ route('athletes.show', $a['id']) }}"><span class="t">{{ $a['name'] }} @if (! empty($a['star']))<x-star />@endif</span>
          <span class="s">@if ($f['sport'] === 'Tous')@td($a['sport']) · @endif{{ \Illuminate\Support\Arr::first($a['fields']) }} · {{ $a['age'] }} {{ __('ans') }} · {{ $a['city'] }}@if (! empty($a['specialty'])) · ★ {{ $a['specialty'] }}@endif</span></a>
        <b class="rating">{{ round($a['rating']) }}</b>
      </li>
    @empty
      <p class="meta">{{ __('Aucun joueur pour ces critères. Élargis un filtre.') }}</p>
    @endforelse
  </ol>
  <p class="meta">{{ __('Pour monter dans le classement : publie des compilations, fais vérifier ton profil et participe aux défis.') }} <a href="{{ route('challenges') }}">{{ __('Défi de la semaine') }} →</a></p>
</div>
@endsection
