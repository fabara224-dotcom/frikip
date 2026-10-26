@extends('layouts.web')

@section('title', __('Inviter mes amis').' – '.config('terrain.app_name'))

@php
  $isRecruiter = $me['role'] === 'recruiter';
  $left = $per - ($count % $per);
  $msg = $isRecruiter
      ? __('Rejoins :app, la plateforme pour recruter les talents d’Afrique de l’Ouest', ['app' => config('terrain.app_name')])
      : __('Inscris-toi sur :app pour montrer tes compilations aux clubs ! Mon lien', ['app' => config('terrain.app_name')]);
@endphp

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:620px">
    <h1>{{ __('Inviter mes amis') }}</h1>
    <div class="card">
      <b style="font-size:20px">🎁 {{ $per }} {{ __('inscrits') }} = {{ $days }} {{ __('jours de') }} {{ $isRecruiter ? 'Premium' : 'Boost' }} {{ __('offerts') }}</b>
      <span class="meta">{{ $isRecruiter ? __('Invite d’autres recruteurs, clubs ou agents.') : __('Invite tes coéquipiers : ton profil remonte en tête du fil et de la recherche.') }}</span>
      <div class="meter" style="margin-top:6px"><i style="width:{{ round(($count % $per) / $per * 100) }}%"></i></div>
      <span><b>{{ $count }}</b> {{ $count > 1 ? __('inscrits grâce à toi') : __('inscrit grâce à toi') }} · {{ __('encore') }} {{ $left }} {{ __('pour la prochaine récompense') }}</span>
    </div>
    @if ($url)
      <div class="section-label">{{ __('Ton lien personnel') }}</div>
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <input class="grow" type="text" readonly value="{{ $url }}" onclick="this.select()" aria-label="{{ __('Ton lien personnel') }}">
        <button class="btn ghost sm" type="button" data-copy="{{ $url }}">{{ __('Copier') }}</button>
      </div>
      <div class="row" style="gap:8px;flex-wrap:wrap;margin-top:12px">
        <a class="btn wa" href="https://wa.me/?text={{ rawurlencode($msg.' : '.$url) }}" target="_blank" rel="noopener">{{ __('Partager sur WhatsApp') }}</a>
        <a class="btn ghost" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($url) }}" target="_blank" rel="noopener">Facebook</a>
        <a class="btn ghost" href="sms:?&body={{ rawurlencode($msg.' : '.$url) }}">SMS</a>
      </div>
    @endif
    <p class="meta" style="margin-top:18px">{{ __('Une inscription compte quand ton ami crée son compte depuis ton lien. Les faux comptes sont supprimés et annulent la récompense.') }}</p>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-copy]').forEach(function (b) {
  b.addEventListener('click', function () { var t = b.dataset.copy; (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { Terrain.toast('Lien copié'); }, function () { Terrain.toast(t); }); });
});
</script>
@endpush
