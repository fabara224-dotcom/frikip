@extends('layouts.web')

@section('title', __('Explorer').' – '.config('terrain.app_name'))

@section('content')
@php
  $isRecruiter = ($me['role'] ?? null) === 'recruiter';
  $cats = [
    ['feed', __('Vidéos'), 'film', 'c-red'],
    ['rankings', __('Joueurs'), 'user', 'c-blue'],
    ['clubs', __('Clubs'), 'stadium', 'c-orange'],
    ['matches', __('Matchs'), 'score', 'c-purple'],
    ['challenges', __('Défis'), 'trophy', 'c-green'],
    ['coaching', __('Entraînement'), 'coach', 'c-sky'],
    ['live', __('En direct'), 'live', 'c-pink'],
    ['events', __('Détections'), 'calendar', 'c-teal'],
    ['stories', __('Recrutés'), 'party', 'c-gold'],
  ];
  $links = [
    ['rankings', __('Classements'), __('Les sportifs les plus suivis et les mieux notés'), 'star'],
    ['challenges', __('Défi de la semaine'), __('Vote pour la meilleure action, le gagnant est mis en avant'), 'trophy'],
    ['live', __('Matchs locaux en direct'), __('Les clubs diffusent leurs matchs dans Frikip'), 'live'],
    ['video.services', __('Montage vidéo'), __('Fais monter ta compilation par un pro'), 'film'],
    ['structure.offer', __('Offre Structure Pro'), __('Pour les clubs, académies et agents'), 'rocket'],
  ];
@endphp
<div class="page gut explore">
  <h1 class="pagetitle">{{ __('Explorer') }}</h1>

  <form class="bigsearch" method="GET" action="{{ route('search') }}" role="search">
    <x-ico name="search" />
    <input type="search" name="q" placeholder="{{ __('Rechercher un joueur, un club, un match…') }}" aria-label="{{ __('Rechercher') }}">
    <button class="btn sm" type="submit">{{ __('Chercher') }}</button>
  </form>
  @unless ($me)
    <p class="meta">{{ __('La recherche de sportifs est réservée aux membres : crée ton compte gratuit en 30 secondes.') }}</p>
  @endunless

  <h2 class="sechead">{{ __('Catégories') }}</h2>
  <div class="catgrid">
    @foreach ($cats as [$route, $label, $icon, $color])
      <a class="cat {{ $color }}" href="{{ route($route) }}"><x-ico :name="$icon" /><span>{{ $label }}</span></a>
    @endforeach
  </div>

  <h2 class="sechead">{{ __('À découvrir') }}</h2>
  <div class="list linklist">
    @foreach ($links as [$route, $title, $sub, $icon])
      <a class="li" href="{{ route($route) }}">
        <span class="lico"><x-ico :name="$icon" /></span>
        <span class="grow"><span class="t">{{ $title }}</span><span class="s">{{ $sub }}</span></span>
        <svg class="chev" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
      </a>
    @endforeach
  </div>

  @if (! $me || ! $isRecruiter)
    <a class="promo" href="{{ $me ? route('publish') : route('phone.login') }}">
      <span><b>{{ __('Découvre les talents de demain') }}</b><small>{{ __('Entraînements · Matchs · Recrutement') }}</small></span>
      <span class="btn sm">{{ $me ? __('Publier ma vidéo') : __('Rejoindre Frikip') }}</span>
    </a>
  @endif
</div>
@endsection
