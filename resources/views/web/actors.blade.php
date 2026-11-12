@extends('layouts.web')

@section('title', __('Acteurs du foot').' – '.config('terrain.app_name'))
@section('description', __('Entraîneurs, arbitres, médecins du sport, kinés, préparateurs physiques et analystes du football en Afrique de l’Ouest.'))

@section('content')
<div class="page gut">
  <p class="meta" style="margin:0"><a href="{{ route('explore') }}">← {{ __('Explorer') }}</a></p>
  <h1>{{ __('Acteurs du foot') }}</h1>
  <p class="pub-lead">{{ __('Le football, ce n’est pas que les joueurs : trouve un entraîneur, un arbitre, un médecin, un kiné ou un préparateur physique.') }}</p>
  <nav class="chips pickchips exchips" aria-label="{{ __('Métier') }}">
    <a class="chip" href="{{ route('actors') }}" aria-current="{{ $metier === '' ? 'true' : 'false' }}">{{ __('Tous') }}</a>
    @foreach ($professions as $p)<a class="chip" href="{{ route('actors', ['metier' => $p]) }}" aria-current="{{ $metier === $p ? 'true' : 'false' }}">{{ __($p) }}</a>@endforeach
  </nav>
  @forelse ($list as $a)
    <a class="listrow" href="{{ route('athletes.show', $a['id']) }}">
      <span style="display:flex;gap:12px;align-items:center;min-width:0"><x-av :name="$a['name']" :sport="$a['sport']" />
        <span style="min-width:0"><b>{{ $a['name'] }}</b>@if ($a['verified']) <x-ver />@endif<small>{{ __($a['profession'] ?? '') }}@if ($a['club'] && $a['club'] !== 'Sans club') · {{ $a['club'] }}@endif · {{ $a['city'] }}</small></span></span>
      <span class="btn sm ghost">{{ __('Voir') }}</span>
    </a>
  @empty
    <div class="empty-state">
      <p>{{ $metier ? __('Pas encore de :m sur MyFootPro.', ['m' => mb_strtolower(__($metier))]) : __('Pas encore d’acteur du foot inscrit.') }}</p>
      @unless ($me)<a class="btn" href="{{ route('register', ['type' => 'acteur']) }}">{{ __('Je suis un acteur du foot : je m’inscris') }}</a>@endunless
    </div>
  @endforelse
</div>
@endsection
