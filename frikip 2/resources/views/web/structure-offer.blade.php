@extends('layouts.web')

@section('title', __('Structure Pro pour académies et clubs').' – '.config('terrain.app_name'))

@php
  $cur = \App\Terrain\Growth\Payments::currency();
  $curLabel = $cur === 'XOF' ? 'FCFA' : $cur;
  $isRecruiter = ($me['role'] ?? null) === 'recruiter';
  $active = $until && \Illuminate\Support\Carbon::parse($until)->isFuture();
@endphp

@section('content')
<div class="page gut">
  <div class="offer-head">
    <span class="pill pro">{{ __('Structure Pro') }}</span>
    <h1>{{ __('Ton académie mérite d’être vue elle aussi.') }}</h1>
    <p class="pub-lead">{{ __('Pour les académies, centres de formation et petits clubs : une page mise en avant, tes détections remplies, et tous les joueurs à portée de message.') }}</p>
    @if ($active)<p class="note">{{ __('Structure Pro est active jusqu’au :d. Un nouveau paiement prolonge la durée.', ['d' => \Illuminate\Support\Carbon::parse($until)->translatedFormat('j F Y')]) }}</p>@endif
  </div>

  <div class="perkgrid">
    <div class="perk"><x-ico name="star" /><b>{{ __('En tête de la liste des clubs') }}</b><span>{{ __('Ta page passe devant les autres, avec le badge Structure Pro.') }}</span></div>
    <div class="perk"><x-ico name="megaphone" /><b>{{ __('Contacts illimités') }}</b><span>{{ __('Écris à tous les sportifs qui t’intéressent. L’accès Premium est inclus.') }}</span></div>
    <div class="perk"><x-ico name="calendar" /><b>{{ __('Détections mises en avant') }}</b><span>{{ __('Les sportifs de ton sport sont prévenus à chaque nouvelle journée de détection.') }}</span></div>
    <div class="perk"><x-ico name="stats" /><b>{{ __('Visites de ta page') }}</b><span>{{ __('Vois combien de personnes découvrent ta structure chaque semaine.') }}</span></div>
  </div>

  <h2>{{ __('Formules') }}</h2>
  <div class="pricegrid">
    @foreach ($plans as $i => [$label, $price, $days, $note])
      <div class="price {{ $i === 1 ? 'best' : '' }}">
        @if ($i === 1)<span class="pill pro">{{ __('Le plus choisi') }}</span>@endif
        <b>{{ __($label) }}</b>
        <span class="amount">{{ number_format($price, 0, ',', ' ') }} <small>{{ $curLabel }}</small></span>
        <span class="meta">{{ __($note) }}</span>
      </div>
    @endforeach
  </div>

  <div class="offer-cta">
    @if (! $me)
      <a class="btn big" href="{{ route('register', ['role' => 'recruiter']) }}">{{ __('Créer le compte de ma structure') }}</a>
      <p class="meta">{{ __('Déjà inscrit ?') }} <a href="{{ route('login') }}">{{ __('Se connecter') }}</a></p>
    @elseif ($isRecruiter)
      @unless ($mine)<p class="note">{{ __('Pense à créer la page de ta structure pour profiter de la mise en avant.') }} <a href="{{ route('clubs.edit') }}">{{ __('Créer ma page') }}</a></p>@endunless
      <a class="btn big" href="{{ route('premium', 'structure') }}">{{ $active ? __('Prolonger Structure Pro') : __('Choisir ma formule') }}</a>
      <p class="meta">{{ __('Paiement par Orange Money ou MTN MoMo.') }}</p>
    @else
      <p class="note">{{ __('Cette offre est réservée aux comptes club, académie ou agent. Tu es sportif ? Découvre plutôt le Boost et le montage vidéo.') }}</p>
      <a class="btn ghost" href="{{ route('video.services') }}">{{ __('Services vidéo') }}</a>
    @endif
  </div>
</div>
@endsection
