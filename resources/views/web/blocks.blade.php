@extends('layouts.web')

@section('title', __('Personnes bloquées').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow">
    <p class="meta" style="margin:0"><a href="{{ route('settings') }}">← {{ __('Paramètres') }}</a></p>
    <h1>{{ __('Personnes bloquées') }}</h1>
    <p class="pub-lead">{{ __('Elles ne peuvent plus t’écrire, et tu ne vois plus leurs vidéos, commentaires ni messages dans les salons.') }}</p>
    @forelse ($rows as $r)
      <div class="listrow"><span><b>{{ $names[$r->blocked] }}</b><small>{{ __('bloqué le :d', ['d' => \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y')]) }}</small></span>
        <form method="POST" action="{{ route('block', $r->blocked) }}">@csrf<button class="btn ghost sm" type="submit">{{ __('Débloquer') }}</button></form></div>
    @empty
      <p class="meta">{{ __('Tu n’as bloqué personne.') }}</p>
    @endforelse
  </div>
</div>
@endsection
