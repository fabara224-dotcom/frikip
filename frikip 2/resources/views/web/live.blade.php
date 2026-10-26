@extends('layouts.web')

@section('title', __('Matchs en direct').' – '.config('terrain.app_name'))
@section('description', __('Regarde en direct les matchs des clubs et académies d’Afrique de l’Ouest.'))

@php
  $when = fn ($s) => $s->starts_at ? \Illuminate\Support\Carbon::parse($s->starts_at)->translatedFormat('D j M · H\hi') : '';
@endphp

@section('content')
<div class="page gut">
  <div class="livehead">
    <div>
      <h1>{{ __('En direct') }}</h1>
      <p class="pub-lead">{{ __('Les matchs des clubs, académies et tournois de la région, en vidéo et avec le score en direct.') }}</p>
    </div>
    @if ($canPublish)<a class="btn" href="{{ route('live.create') }}">{{ __('Diffuser un match') }}</a>@endif
  </div>

  @if ($onAir->count())
    <div class="livegrid">
      @foreach ($onAir as $s)
        <a class="livecard on @if ($s->thumbnail) thumb @endif" href="{{ route('live.show', $s->id) }}" @if ($s->thumbnail) style="--thumb:url('{{ $s->thumbnail }}')" @endif>
          <span class="livebadge"><i aria-hidden="true"></i>{{ __('En direct') }}@if ($s->minute) · {{ $s->minute }}@endif</span>
          @if (\App\Terrain\Growth\Live::hasScore($s))
            <span class="lscore"><b>{{ $s->home }}</b><em>{{ $s->home_score }} – {{ $s->away_score }}</em><b>{{ $s->away }}</b></span>
          @else
            <span class="ltitle">{{ \App\Terrain\Growth\Live::label($s) }}</span>
          @endif
          <small>@td($s->sport)@if ($s->competition) · {{ $s->competition }}@endif @if ($s->channel) · {{ $s->channel }}@endif · {{ \App\Terrain\DataLang::t((string) $s->country) }}</small>
        </a>
      @endforeach
    </div>
  @else
    <div class="empty-state live-empty">
      <p><b>{{ __('Aucun match en direct pour l’instant.') }}</b></p>
      <p class="meta">{{ __('Les prochains directs sont annoncés ci-dessous. Tu es prévenu au coup d’envoi.') }}</p>
    </div>
  @endif

  @if ($big)
    <h2>{{ __('Grands championnats aujourd’hui') }}</h2>
    <div class="bigmatches">
      @foreach ($big as $m)
        <a class="bigm" href="{{ route('matches.show', $m['id']) }}">
          <span class="bm-top"><span>@if (! empty($m['league_logo']))<img src="{{ $m['league_logo'] }}" alt="" loading="lazy" onerror="this.remove()">@endif{{ $m['competition_name'] }}</span>
            @if ($m['status'] === 'live')<span class="livebadge"><i></i>{{ $m['minute'] }}'</span>@else<span>{{ $m['date_label'] }}</span>@endif</span>
          <span class="lscore"><b>{{ $m['home'] }}</b><em>{{ $m['status'] === 'upcoming' ? '–' : $m['hs'].' – '.$m['as'] }}</em><b>{{ $m['away'] }}</b></span>
          <span class="bm-tv">@if ($m['inside'])● {{ __('À regarder dans :app', ['app' => config('terrain.app_name')]) }}@elseif ($m['tv'])📺 {{ $m['tv'] }}@else{{ __('Score en direct') }}@endif</span>
        </a>
      @endforeach
    </div>
  @endif

  @if ($soon->count())
    <h2>{{ __('À venir') }}</h2>
    <div class="livelist">
      @foreach ($soon as $s)
        <a class="listrow" href="{{ route('live.show', $s->id) }}"><span><b>{{ \App\Terrain\Growth\Live::label($s) }}</b><small>{{ $when($s) }} · @td($s->sport)@if ($s->competition) · {{ $s->competition }}@endif · {{ \App\Terrain\DataLang::t((string) $s->country) }}</small></span><span class="pill">{{ __('À venir') }}</span></a>
      @endforeach
    </div>
  @endif

  @if ($past->count())
    <h2>{{ __('Revoir') }}</h2>
    <div class="livelist">
      @foreach ($past as $s)
        <a class="listrow" href="{{ route('live.show', $s->id) }}"><span><b>@if (\App\Terrain\Growth\Live::hasScore($s)){{ $s->home }} {{ $s->home_score }} – {{ $s->away_score }} {{ $s->away }}@else{{ \App\Terrain\Growth\Live::label($s) }}@endif</b><small>{{ $when($s) }}@if ($s->competition) · {{ $s->competition }}@endif</small></span><span class="pill">{{ __('Terminé') }}</span></a>
      @endforeach
    </div>
  @endif

  @if ($mine->count())
    <h2>{{ __('Mes directs') }}</h2>
    <div class="livelist">
      @foreach ($mine as $s)
        <a class="listrow" href="{{ route('live.manage', $s->id) }}"><span><b>{{ $s->title }}</b><small>{{ $when($s) }}</small></span><span class="pill {{ $s->approved ? '' : 'warn' }}">{{ $s->approved ? __(\App\Terrain\Growth\Live::STATUS[$s->status]) : __('En attente de validation') }}</span></a>
      @endforeach
    </div>
  @endif
</div>
@endsection
