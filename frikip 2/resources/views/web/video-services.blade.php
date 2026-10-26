@extends('layouts.web')

@section('title', __('Montage vidéo').' – '.config('terrain.app_name'))

@php
  $cur = \App\Terrain\Growth\Payments::currency();
  $curLabel = $cur === 'XOF' ? 'FCFA' : $cur;
@endphp

@section('content')
<div class="page gut">
  <div class="offer-head">
    <h1>{{ __('On monte ta compilation pour toi.') }}</h1>
    <p class="pub-lead">{{ __('Envoie tes vidéos brutes depuis ton téléphone. Notre équipe garde tes meilleures actions et te livre une compilation prête pour les recruteurs.') }}</p>
  </div>

  <div class="pricegrid">
    @foreach ($packages as $i => [$label, $price, $days, $note])
      <div class="price {{ $i === 1 ? 'best' : '' }}">
        @if ($i === 1)<span class="pill pro">{{ __('Le plus choisi') }}</span>@endif
        <b>{{ __($label) }}</b>
        <span class="amount">{{ number_format($price, 0, ',', ' ') }} <small>{{ $curLabel }}</small></span>
        <span class="meta">{{ __($note) }}</span>
        <a class="btn {{ $i === 1 ? '' : 'ghost' }}" href="{{ $me ? route('video.order', $i) : route('login') }}">{{ __('Commander') }}</a>
      </div>
    @endforeach
  </div>

  <h2>{{ __('Comment ça marche') }}</h2>
  <ol class="howto">
    <li><b>{{ __('Choisis ta formule et envoie tes vidéos') }}</b><span>{{ __('Jusqu’à 5 vidéos, ou un lien Google Drive. Dis-nous ce qu’il faut montrer : ton numéro, ton poste, tes meilleurs moments.') }}</span></li>
    <li><b>{{ __('Paie par Orange Money ou MTN MoMo') }}</b><span>{{ __('Tu reçois un reçu dans « Mes paiements ».') }}</span></li>
    <li><b>{{ __('Reçois ta compilation') }}</b><span>{{ __('En général sous 3 jours. Tu es prévenu par notification et sur WhatsApp, puis tu la publies sur ton profil.') }}</span></li>
  </ol>
  @if ($me)<p><a href="{{ route('video.orders') }}">{{ __('Suivre mes commandes') }}</a></p>@endif
</div>
@endsection
