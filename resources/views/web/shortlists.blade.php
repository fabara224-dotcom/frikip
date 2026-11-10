@extends('layouts.web')

@section('title', __('Mes listes de joueurs').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow">
    <h1>{{ __('Mes listes de joueurs') }}</h1>
    <p class="pub-lead">{{ __('Range les joueurs qui t’intéressent par liste : « Attaquants U20 », « Essai de juin »… Ajoute-les depuis la recherche ou leur profil.') }}</p>
    <form method="POST" action="{{ route('shortlists.store') }}" class="card row" style="gap:8px">@csrf
      <input name="name" maxlength="80" required placeholder="{{ __('Nom de la nouvelle liste') }}" aria-label="{{ __('Nom de la nouvelle liste') }}" style="flex:1;min-width:0">
      <button class="btn" type="submit">{{ __('Créer') }}</button>
    </form>
    @forelse ($lists as $l)
      <a class="listrow" href="{{ route('shortlists.show', $l->id) }}"><span><b>{{ $l->name }}</b><small>{{ trans_choice(':n joueur|:n joueurs', $l->count, ['n' => $l->count]) }} · {{ __('modifiée le :d', ['d' => \Illuminate\Support\Carbon::parse($l->updated_at)->format('d/m/Y')]) }}</small></span><span class="btn sm ghost">{{ __('Ouvrir') }}</span></a>
    @empty
      <p class="meta">{{ __('Aucune liste pour l’instant.') }}</p>
    @endforelse
    <p style="margin-top:18px"><a href="{{ route('contacts') }}">{{ __('Historique de mes contacts') }} →</a></p>
  </div>
</div>
@endsection
