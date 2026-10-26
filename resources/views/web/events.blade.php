@extends('layouts.web')

@section('title', __('Détections').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="row" style="justify-content:space-between;align-items:center">
    <h1>{{ __('Journées de détection') }}</h1>
    @if ($me && $me['role'] === 'recruiter')<a class="btn sm" href="{{ route('clubs.edit') }}">{{ __('Organiser une détection') }}</a>@endif
  </div>
  <p class="meta">{{ __('Inscris-toi en ligne, reçois ton billet avec QR code et présente-le le jour J. Les détections sont toujours gratuites.') }}</p>
  <nav class="chips" aria-label="{{ __('Sport') }}">
    <a class="chip" href="{{ route('events') }}" aria-current="{{ $sport ? 'false' : 'true' }}">{{ __('Tous') }}</a>
    @foreach ($sports as $s)<a class="chip" href="{{ route('events', ['sport' => $s]) }}" aria-current="{{ $sport === $s ? 'true' : 'false' }}">{{ $s }}</a>@endforeach
  </nav>
  <div class="list" style="margin-top:10px">
    @forelse ($rows as $e)
      @php
        $d = \Illuminate\Support\Carbon::parse($e->date);
        $n = $counts[$e->id] ?? 0;
      @endphp
      <a class="li evt" href="{{ route('events.show', $e->id) }}">
        <span class="datebox"><b>{{ $d->format('d') }}</b>{{ $d->translatedFormat('M') }}</span>
        <span class="grow" style="min-width:0"><span class="t">{{ $e->title }}</span>
          <span class="s">{{ $e->org_name ?? __('Organisateur') }} @if ($e->org_verified)✓@endif · {{ $e->sport }} · {{ $e->city }} · {{ $d->translatedFormat('l') }} {{ $e->time }}@if ($e->age_min) · {{ $e->age_min }}-{{ $e->age_max }} {{ __('ans') }}@endif</span></span>
        @if (isset($mine[$e->id]))<span class="pill ok">{{ __('Inscrit') }}</span>@else<span class="pill">{{ max(0, $e->capacity - $n) }} {{ __('places') }}</span>@endif
      </a>
    @empty
      <p class="meta">{{ __('Aucune détection programmée pour le moment.') }}</p>
    @endforelse
  </div>
</div>
@endsection
