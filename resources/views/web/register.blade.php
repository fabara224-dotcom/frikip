@extends('layouts.web')

@section('title', __('Créer un compte').' – '.config('terrain.app_name'))
@section('description', __('Crée ton compte MyFootPro en 1 minute : joueur, supporter, club, recruteur, agent, entraîneur, arbitre, médecin… Avec Google, Facebook, TikTok, ton numéro ou ton e-mail.'))

@php
  // Erreurs après envoi du formulaire e-mail : on rouvre l'étape 3 ; type déjà choisi : étape 2
  $start = $errors->any() && old('type') ? 3 : ($type ? 2 : 1);
@endphp

@section('content')
<div class="page gut">
  <div class="narrow signup" style="max-width:680px">
    <h1>{{ __('Créer mon compte MyFootPro') }}</h1>
    <p class="pub-lead">{{ __('C’est gratuit et ça prend une minute. MyFootPro réunit tout le monde du football : joueurs, supporters, clubs, recruteurs, agents, entraîneurs, arbitres, médecins…') }}</p>

    <ol class="steps" aria-label="{{ __('Étapes') }}">
      <li class="on"><span>1</span>{{ __('Qui es-tu ?') }}</li>
      <li><span>2</span>{{ __('Comment ?') }}</li>
      <li><span>3</span>{{ __('Ton profil') }}</li>
    </ol>

    <form method="POST" action="{{ route('register') }}" class="card" id="su" data-start="{{ $start }}" novalidate>@csrf
      {{-- Étape 1 --}}
      <section data-step="1">
        <h2>{{ __('Qui es-tu ?') }}</h2>
        <p class="meta" style="margin-top:-4px">{{ __('Choisis ce qui te ressemble le plus. Chaque compte a ses propres outils.') }}</p>
        @include('web.partials.signup-types')
        @error('type')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        <button class="btn big" type="button" data-goto="2" @disabled(! $type)>{{ __('Continuer') }}</button>
      </section>

      {{-- Étape 2 --}}
      <section data-step="2" hidden>
        <h2>{{ __('Comment veux-tu créer ton compte ?') }}</h2>
        <p class="meta" style="margin-top:-4px">{{ __('Choisis le plus simple pour toi. La prochaine fois, tu te connecteras de la même façon.') }}</p>
        @foreach ($types as $k => $t)<p class="sfhelp" data-types="{{ $k }}" hidden><b>{{ $t[4] }} {{ __($t[1]) }}</b> · <button type="button" class="linkbtn" data-goto="1">{{ __('changer') }}</button></p>@endforeach
        @include('web.partials.social-buttons', ['type' => $type])
        <div class="ordiv"><span>{{ __('ou') }}</span></div>
        <button class="authbtn mail" type="button" data-goto="3">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
          {{ __('Avec mon adresse e-mail') }}</button>
        <p class="meta" style="margin:12px 0 0">🔒 {{ __('Ton numéro et ton e-mail ne sont jamais affichés sur ton profil. Personne ne peut te demander d’argent pour un essai sur MyFootPro.') }}</p>
        <button class="linkbtn" type="button" data-goto="1" style="margin-top:12px">← {{ __('Retour') }}</button>
      </section>

      {{-- Étape 3 --}}
      <section data-step="3" hidden>
        <h2>{{ __('Ton profil') }}</h2>
        <p class="meta" style="margin-top:-4px">{{ __('Seules quelques questions maintenant. Tu compléteras le reste plus tard.') }}</p>
        @include('web.partials.signup-fields', ['credentials' => true])
        <button class="btn big" type="submit">{{ __('Créer mon compte') }}</button>
        <button class="linkbtn" type="button" data-goto="2" style="margin-top:8px">← {{ __('Retour') }}</button>
      </section>
    </form>
    <p>{{ __('Déjà un compte ?') }} <a href="{{ route('login') }}">{{ __('Se connecter') }}</a></p>
  </div>
</div>
@endsection

@push('scripts')
@include('web.partials.signup-js')
@endpush
