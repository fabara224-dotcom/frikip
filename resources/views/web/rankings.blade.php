@extends('layouts.web')

@section('title', __('Classements').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <h1>{{ __('Classements') }}</h1>
  <p class="meta">{{ __('Top') }} {{ $f['poste'] ? mb_strtolower($f['poste']).'s' : __('joueurs') }} {{ $f['sport'] }} {{ $f['cat'] !== 'Tous' ? $f['cat'] : '' }} {{ $f['city'] ? __('de').' '.$f['city'] : ($f['country'] !== 'Tous' ? '– '.$f['country'] : '') }}.
    {{ __('Note calculée avec la fiche, les vues, les « j’aime » et la vérification du profil.') }}</p>

  <form method="GET" action="{{ route('rankings') }}" class="filterbar">
    <select name="sport" aria-label="{{ __('Sport') }}" onchange="this.form.submit()">@foreach ($sports as $s)<option @selected($f['sport'] === $s)>{{ $s }}</option>@endforeach</select>
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
          <span class="s">{{ \Illuminate\Support\Arr::first($a['fields']) }} · {{ $a['age'] }} {{ __('ans') }} · {{ $a['city'] }}@if (! empty($a['specialty'])) · ★ {{ $a['specialty'] }}@endif</span></a>
        <b class="rating">{{ round($a['rating']) }}</b>
      </li>
    @empty
      <p class="meta">{{ __('Aucun joueur pour ces critères. Élargis un filtre.') }}</p>
    @endforelse
  </ol>
  <p class="meta">{{ __('Pour monter dans le classement : publie des compilations, fais vérifier ton profil et participe aux défis.') }} <a href="{{ route('challenges') }}">{{ __('Défi de la semaine') }} →</a></p>
</div>
@endsection
