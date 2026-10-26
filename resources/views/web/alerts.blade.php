@extends('layouts.web')

@section('title', __('Mes alertes').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:680px">
    <h1>{{ __('Mes alertes') }}</h1>
    <p class="meta">{{ __('Chaque matin, tu es prévenu (cloche, e-mail, SMS ou WhatsApp selon tes préférences) des nouveaux profils qui correspondent à tes recherches.') }}
      <a href="{{ route('search') }}">{{ __('Créer une alerte depuis la recherche') }} →</a></p>
    <div class="list">
      @forelse ($rows as $s)
        <div class="li"><span class="nico">📣</span>
          <span class="grow"><span class="t">{{ $s->label }}</span><span class="s">{{ __('Créée le') }} {{ \Illuminate\Support\Carbon::parse($s->created_at)->format('d/m/Y') }}</span></span>
          <a class="btn ghost sm" href="{{ route('search', json_decode($s->filters, true)) }}">{{ __('Voir') }}</a>
          <form method="POST" action="{{ route('alerts.destroy', $s->id) }}">@csrf<button class="btn ghost sm" type="submit" aria-label="{{ __('Supprimer') }}">✕</button></form>
        </div>
      @empty
        <p class="meta">{{ __('Aucune alerte. Fais une recherche puis touche « Créer une alerte ».') }}</p>
      @endforelse
    </div>
  </div>
</div>
@endsection
