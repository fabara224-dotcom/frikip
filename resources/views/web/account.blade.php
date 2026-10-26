@extends('layouts.web')

@section('title', 'Compte recruteur – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="cols">
    <div>
      <div style="display:flex;gap:16px;align-items:center">
        <x-av :name="$me['name']" sport="Football" lg />
        <div><h1 style="margin:0;display:flex;gap:8px;align-items:center">{{ $me['name'] }} @if ($me['verified'])<x-ver />@endif</h1>
          <div class="meta">Compte recruteur · {{ $me['email'] }}</div></div>
      </div>
      @unless ($me['verified'])
        <p class="note">Ton compte n'est pas encore vérifié. <a href="{{ route('verification') }}">Envoie ta licence de club ou d'agent</a> pour pouvoir écrire aux sportifs et publier des offres.</p>
      @endunless
      <div class="quick">
        <a href="{{ route('search') }}"><x-ico name="search" /><span>Rechercher</span></a>
        <a href="{{ route('alerts') }}"><x-ico name="megaphone" /><span>Mes alertes</span></a>
        <a href="{{ route('clubs.edit') }}"><x-ico name="stadium" /><span>Ma page club</span></a>
        <a href="{{ route('events') }}"><x-ico name="calendar" /><span>Détections</span></a>
        <a href="{{ route('rankings') }}"><x-ico name="star" /><span>Classements</span></a>
        <a href="{{ route('referral') }}"><x-ico name="gift" /><span>Inviter</span></a>
      </div>

      <div class="card" style="margin-top:16px;background:var(--ink);color:var(--surface);border-color:var(--ink)">
        <b style="font-family:var(--f-display);font-size:22px;font-stretch:115%">{{ ! empty($me['premium']) ? 'Premium actif' : 'Recruteur Premium' }}</b>
        <span>Contacts illimités, filtres avancés, alertes de nouveaux profils, statistiques de tes offres.</span>
        @if (empty($me['premium']))<a class="btn" style="align-self:flex-start" href="{{ route('premium', 'premium') }}">Passer Premium</a>@endif
      </div>
      <a class="probanner" href="{{ route('structure.offer') }}" style="margin-top:12px"><span><b>{{ __('Académie ou petit club ?') }}</b>{{ __('Structure Pro : ta page en tête de la liste des clubs, contacts illimités inclus.') }}</span><span class="btn sm">{{ __('Voir l’offre') }}</span></a>

      <div class="section-label">Favoris</div>
      <div class="list">
        @forelse ($favorites as $f)
          <a class="li" href="{{ route('athletes.show', $f['id']) }}"><x-av :name="$f['name']" :sport="$f['sport']" />
            <span class="grow"><span class="t">{{ $f['name'] }}</span><span class="s">{{ $f['sport'] }} · {{ \Illuminate\Support\Arr::first($f['fields']) }}</span></span></a>
        @empty
          <p class="meta">Ajoute des profils en favoris depuis leur fiche ou le fil.</p>
        @endforelse
      </div>
    </div>
    <aside class="card">
      <div class="spec">
        <div class="r"><span>Offres publiées</span><b>{{ $offersCount }}</b></div>
        <div class="r"><span>Contacts</span><b>{{ ! empty($me['premium']) ? 'Illimités' : config('terrain.free_contacts').' gratuits' }}</b></div>
        <div class="r"><span>Vérification</span><b>{{ $me['verified'] ? 'Validée' : 'En attente' }}</b></div>
      </div>
      <a href="{{ route('search') }}">Rechercher des talents</a>
      <a href="{{ route('offers') }}">Mes offres et candidats</a>
      <a href="{{ route('messages') }}">Mes messages</a>
      @if ($realAccounts ?? false)<a href="{{ route('profile.edit') }}">{{ __('Modifier mon profil') }}</a>@endif
      <a href="{{ route('payments.history') }}">{{ __('Mes paiements') }}</a>
      <a href="{{ route('settings') }}">Paramètres et confidentialité</a>
      <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn ghost" type="submit">Se déconnecter</button></form>
    </aside>
  </div>
</div>
@endsection
