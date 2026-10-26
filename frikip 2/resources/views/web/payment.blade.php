@extends('layouts.web')

@section('title', 'Paiement – '.config('terrain.app_name'))

@php
  $title = ['premium' => 'Recruteur Premium', 'structure' => 'Abonnement Structure Pro', 'boost' => 'Booster mon profil', 'sans_pub' => __('Frikip sans pub')][$product];
  $perks = [
      'premium' => ['Contacts illimités avec les sportifs', 'Alertes de nouveaux profils', 'Statistiques de tes offres', 'Badge Premium sur tes offres'],
      'structure' => ['Page de ta structure en tête de la liste des clubs, avec le badge Structure Pro', 'Contacts illimités avec les sportifs (Premium inclus)', 'Tes détections mises en avant', 'Statistiques de visites de ta page'],
      'boost' => ['Ton profil en tête du fil et de la recherche', 'Plus de vues de recruteurs', 'Badge « Boosté »'],
      'sans_pub' => [__('Aucune publicité dans le fil ni pendant les matchs en direct'), __('Les vidéos s’enchaînent sans interruption'), __('Moins de données mobiles consommées')],
  ][$product];
  $cur = \App\Terrain\Growth\Payments::currency();
  $curLabel = $cur === 'XOF' ? 'FCFA' : $cur;
  $until = ['premium' => $member->premium_until ?? null, 'structure' => $member->structure_until ?? null, 'boost' => $member->boost_until ?? null, 'sans_pub' => $member->adfree_until ?? null][$product];
@endphp

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:560px">
    <h1>{{ $title }}</h1>
    @if ($until && \Illuminate\Support\Carbon::parse($until)->isFuture())
      <p class="note">Actif jusqu'au {{ \Illuminate\Support\Carbon::parse($until)->format('d/m/Y') }}. Un nouveau paiement prolonge la durée.</p>
    @endif
    <ul class="perks">@foreach ($perks as $perk)<li>{{ $perk }}</li>@endforeach</ul>

    @unless ($countryOk)
      <p class="note">{{ __('Le paiement en ligne n’est pas encore disponible pour ce pays (:c). Il arrive bientôt : écris-nous si tu veux payer autrement.', ['c' => $country]) }} <a href="{{ route('support') }}">{{ __('Aide et contact') }}</a></p>
    @endunless
    <form method="POST" action="{{ route('premium', $product) }}" class="card" @unless ($countryOk) hidden @endunless>@csrf
      <div class="plans">
        @foreach ($plans as $i => [$label, $price, $days, $note])
          <label class="opt"><input type="radio" name="plan" value="{{ $i }}" @checked(old('plan', '0') == $i)><span>{{ $label }}</span><b>{{ number_format($price, 0, ',', ' ') }} {{ $curLabel }}</b><span class="meta">{{ $note }}</span></label>
        @endforeach
      </div>
      <b>Moyen de paiement</b>
      @foreach (['om' => ['Orange Money', '#FF7900'], 'momo' => ['MTN MoMo', '#FFCB05'], 'card' => ['Carte bancaire', '#0B7A4B']] as $k => [$label, $color])
        <label class="opt" style="flex-direction:row;align-items:center;gap:10px"><input type="radio" name="method" value="{{ $k }}" @checked(old('method', 'om') === $k)><span style="width:20px;height:20px;border-radius:50%;background:{{ $color }}"></span>{{ $label }}</label>
      @endforeach
      <label for="phone">Numéro Mobile Money<input id="phone" name="phone" type="tel" value="{{ old('phone', $member->phone ?? '') }}" placeholder="{{ __('Numéro avec ou sans l’indicatif +:d', ['d' => \App\Terrain\Region::dial($country)]) }}" autocomplete="tel" required></label>
      @error('phone')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
      <button class="btn" type="submit">Continuer vers le paiement</button>
      @if ($live)
        <p class="meta" style="margin:0">🔒 {{ __('Tu vas être redirigé vers la page sécurisée de CinetPay pour valider avec ton code Orange Money ou MTN MoMo. Un reçu t’est envoyé et reste dans « Mes paiements ».') }}</p>
      @elseif ($simulation)
        <p class="meta" style="margin:0">Paiement <b>simulé</b> : aucun argent ne sera débité (les clés CinetPay ne sont pas encore configurées).</p>
      @else
        <p class="note" style="margin:0">{{ __('Le paiement en ligne n’est pas encore ouvert sur ce site. Réessaie bientôt.') }}</p>
      @endif
      <a class="meta" href="{{ route('payments.history') }}">{{ __('Mes paiements') }}</a>
    </form>
  </div>
</div>
@endsection
