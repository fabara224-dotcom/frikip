@extends('layouts.web')

@section('title', __('Clubs et académies').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="row" style="justify-content:space-between;align-items:center">
    <h1>{{ __('Clubs, académies et agents') }}</h1>
    @if ($me && $me['role'] === 'recruiter')<a class="btn sm" href="{{ route('clubs.edit') }}">{{ __('Ma page') }}</a>@endif
  </div>
  <nav class="chips" aria-label="{{ __('Filtrer') }}">
    <a class="chip" href="{{ route('clubs') }}" aria-current="{{ $type ? 'false' : 'true' }}">{{ __('Tous') }}</a>
    @foreach ($types as $k => $l)<a class="chip" href="{{ route('clubs', ['type' => $k]) }}" aria-current="{{ $type === $k ? 'true' : 'false' }}">{{ __($l) }}</a>@endforeach
  </nav>
  <a class="probanner" href="{{ route('structure.offer') }}"><span><b>{{ __('Tu diriges une académie ou un club ?') }}</b>{{ __('Mets ta structure en avant et contacte les joueurs sans limite avec Structure Pro.') }}</span><span class="btn sm">{{ __('Voir l’offre') }}</span></a>
  <div class="mgrid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr));margin-top:12px">
    @forelse ($rows as $o)
      <a class="card orgcard" href="{{ route('clubs.show', $o->slug) }}">
        <span class="orglogo" style="background:{{ $o->color }}">{{ \App\Terrain\SportFields::initials($o->name) }}</span>
        <b>{{ $o->name }} @if ($o->verified)<x-ver />@endif</b>
        @if ($o->owner_key && in_array($o->owner_key, $pro, true))<span class="pill pro">{{ __('Structure Pro') }}</span>@endif
        <span class="meta">{{ __($types[$o->type] ?? $o->type) }} · {{ $o->sport }} · {{ $o->city }}</span>
        @if ($o->palmares)<span class="meta">🏆 {{ $o->palmares }}</span>@endif
      </a>
    @empty
      <p class="meta">{{ __('Aucune page pour l’instant.') }}</p>
    @endforelse
  </div>
</div>
@endsection
