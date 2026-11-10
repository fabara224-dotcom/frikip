{{-- Boutons « Continuer avec Google / Facebook / TikTok / mon numéro ». $type (facultatif) : type de compte choisi à l'étape 1. --}}
@php
  $type ??= null;
  $q = $type ? ['type' => $type] : [];
  $names = \App\Terrain\SocialLogin::NAMES;
@endphp
<div class="authbtns">
  @foreach ($providers as $p)
    <a class="authbtn {{ $p }}" href="{{ route('social.redirect', [$p] + $q) }}" data-auth="{{ $p }}">{{ __('Continuer avec :p', ['p' => $names[$p]]) }}</a>
  @endforeach
  <a class="authbtn phone" href="{{ route('signup.phone', $q) }}" data-auth="phone">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/></svg>
    {{ __('Continuer avec mon numéro de téléphone') }}</a>
</div>
