@extends('layouts.web')

@section('title', $list->name.' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow">
    <p class="meta" style="margin:0"><a href="{{ route('shortlists') }}">← {{ __('Mes listes') }}</a></p>
    <h1>{{ $list->name }}</h1>
    @if (count($rows) >= 2)
      <p><a class="btn ghost sm" href="{{ route('compare', ['ids' => implode(',', array_slice(array_map(fn ($r) => $r['a']['id'], $rows), 0, 2))]) }}">{{ __('Comparer les deux premiers') }}</a></p>
    @endif
    @forelse ($rows as $r)
      @php($a = $r['a'])
      <div class="card" style="gap:8px">
        <a class="listrow" style="border:0;padding:0" href="{{ route('athletes.show', $a['id']) }}"><span style="display:flex;gap:12px;align-items:center"><x-av :name="$a['name']" :sport="$a['sport']" />
          <span><b>{{ $a['name'] }}</b>@if ($a['verified']) <x-ver />@endif<small>{{ __($a['fields']['Poste'] ?? '') }} · {{ __($a['fields']['Pied fort'] ?? '') }} · {{ $a['age'] }} {{ __('ans') }} · {{ $a['club'] }} · {{ $a['city'] }}</small></span></span></a>
        <form method="POST" action="{{ route('shortlists.note', $list->id) }}" class="row" style="gap:8px">@csrf<input type="hidden" name="athlete" value="{{ $a['id'] }}">
          <input name="note" value="{{ $r['note'] }}" maxlength="300" placeholder="{{ __('Ta note (privée) : vu au tournoi de Kipé, à revoir…') }}" aria-label="{{ __('Note') }}" style="flex:1;min-width:0">
          <button class="btn ghost sm" type="submit">{{ __('Enregistrer') }}</button></form>
        <form method="POST" action="{{ route('shortlists.toggle', $list->id) }}">@csrf<input type="hidden" name="athlete" value="{{ $a['id'] }}"><button class="linkbtn meta" type="submit">{{ __('Retirer de la liste') }}</button></form>
      </div>
    @empty
      <p class="meta">{{ __('Liste vide. Ajoute des joueurs depuis la recherche ou leur profil (bouton « ＋ Liste »).') }}</p>
    @endforelse
    <form method="POST" action="{{ route('shortlists.destroy', $list->id) }}" style="margin-top:20px" onsubmit="return confirm(@js(__('Supprimer cette liste ?')))">@csrf<button class="linkbtn meta" type="submit">{{ __('Supprimer la liste') }}</button></form>
  </div>
</div>
@endsection
