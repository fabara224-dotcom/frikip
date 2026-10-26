@extends('layouts.web')

@section('title', __('Défis').' – '.config('terrain.app_name'))
@section('description', __('Participe aux défis vidéo de la semaine, vote pour tes joueurs préférés et gagne des lots.'))

@php
  $main = $cards[0] ?? null;
  $rest = array_slice($cards, 1);
  $endOf = fn ($c) => \Illuminate\Support\Carbon::parse($c->ends_on)->endOfDay();
@endphp

@section('content')
<div class="page gut">
  @include('web.partials.defi-coach-switch', ['on' => 'defis'])

  @if ($main)
    @php
      $c = $main['c'];
    @endphp
    <section class="defihero {{ $main['top'] ? '' : 'slim' }}">
      <div class="dh-main">
        <span class="dh-kind">{{ ($c->kind ?? 'semaine') === 'special' ? __('Défi spécial') : __('Défi de la semaine') }}@if ($c->sport) · @td($c->sport)@endif</span>
        <h1>{{ __($c->title) }}</h1>
        @php
        $desc = $c->description ?: (\App\Terrain\Growth\Challenges::THEMES[$c->theme][2] ?? '');
      @endphp
      @if ($desc)<p>{{ __($desc) }}</p>@endif
        @if ($c->prize || $boost)<p class="dh-prize"><x-ico name="trophy" /> {{ __('À gagner') }} : <b>{{ $c->prize ?: '' }}{{ $c->prize && $boost ? ' + ' : '' }}{{ $boost ? __(':n jours de Boost', ['n' => $boost]) : '' }}</b>@if ($c->sponsor) · {{ __('offert par :s', ['s' => $c->sponsor]) }}@endif</p>@endif
        <div class="countdown" data-end="{{ $endOf($c)->toIso8601String() }}" aria-label="{{ __('Temps restant') }}"><span><b>–</b>{{ __('jours') }}</span><span><b>–</b>{{ __('heures') }}</span><span><b>–</b>{{ __('min') }}</span></div>
        <div class="dh-cta">
          <a class="btn big" href="{{ route('challenges.show', $c->id) }}">{{ $athlete ? __('Participer ou voter') : __('Voir et voter') }}</a>
          <span class="dh-stats">{{ trans_choice(':n participant|:n participants', $main['count'], ['n' => $main['count']]) }} · {{ trans_choice(':n vote|:n votes', $main['votes'], ['n' => $main['votes']]) }}</span>
        </div>
      </div>
      @if ($main['top'])
        <ol class="dh-podium">
          @foreach ($main['top'] as $i => $en)
            <li><a href="{{ route('challenges.show', $c->id) }}#e{{ $en['entry']->id }}"><span class="pos">{{ $i + 1 }}</span><x-av :name="$en['athlete']['name']" :sport="$en['athlete']['sport']" /><span class="pn">{{ $en['athlete']['name'] }}<small>{{ trans_choice(':n vote|:n votes', $en['entry']->votes, ['n' => $en['entry']->votes]) }}</small></span></a></li>
          @endforeach
        </ol>
      @endif
    </section>
  @endif

  @if ($rest)
    <h2>{{ __('Autres défis en cours') }}</h2>
    <div class="defigrid">
      @foreach ($rest as $card)
        @php
          $c = $card['c'];
        @endphp
        <a class="deficard" href="{{ route('challenges.show', $c->id) }}">
          <span class="dc-top">@if ($c->sponsor)<span class="pill pro">{{ $c->sponsor }}</span>@endif @if ($c->sport)<span class="pill">@td($c->sport)</span>@endif @if ($c->country)<span class="pill">{{ \App\Terrain\DataLang::t($c->country) }}</span>@endif</span>
          <b>{{ __($c->title) }}</b>
          @if ($c->prize)<span class="dc-prize">🏆 {{ $c->prize }}</span>@endif
          <span class="meta">{{ __('Fin :d', ['d' => $endOf($c)->diffForHumans()]) }} · {{ trans_choice(':n participant|:n participants', $card['count'], ['n' => $card['count']]) }}</span>
        </a>
      @endforeach
    </div>
  @endif

  @if ($mine)
    <h2>{{ __('Mes participations') }}</h2>
    @foreach ($mine as $m)
      @php
        $won = $m->winner_entry_id && (int) $m->winner_entry_id === (int) $m->entry_id;
      @endphp
      <a class="listrow" href="{{ route('challenges.show', $m->id) }}"><span><b>{{ __($m->title) }}</b><small>{{ trans_choice(':n vote|:n votes', $m->votes, ['n' => $m->votes]) }}@if ($m->excluded) · {{ __('participation retirée par l’équipe') }}@endif</small></span>
        <span class="pill {{ $won ? 'pro' : '' }}">{{ $won ? '🏆 '.__('Gagné') : ($m->status === 'ouvert' ? __('En cours') : __('Terminé')) }}</span></a>
    @endforeach
  @elseif ($athlete && ! count($athlete['videos']))
    <p class="note"><a href="{{ route('publish') }}">{{ __('Publie une compilation') }}</a> {{ __('pour participer.') }}</p>
  @endif

  <a class="coachpush" href="{{ route('coaching') }}">
    <span><b>{{ __('Prépare ton prochain défi') }}</b>{{ __('Ton programme d’entraînement gratuit selon ton poste et tes chiffres.') }}</span>
    <span class="btn ghost sm">{{ __('Coaching') }}</span>
  </a>

  @if (count($winners))
    <h2>{{ __('Les gagnants') }}</h2>
    <div class="halloffame">
      @foreach ($winners as $w)
        <a class="hof" href="{{ route('athletes.show', $w['athlete']['id']) }}">
          <span class="hof-av"><x-av :name="$w['athlete']['name']" :sport="$w['athlete']['sport']" lg /><i aria-hidden="true">🏆</i></span>
          <b>{{ $w['athlete']['name'] }}</b>
          <span class="meta">{{ __($w['title']) }}</span>
          <small>{{ trans_choice(':n vote|:n votes', $w['votes'], ['n' => $w['votes']]) }} · {{ \Illuminate\Support\Carbon::parse($w['ends_on'])->translatedFormat('j M') }}</small>
        </a>
      @endforeach
    </div>
  @endif
  <x-fabara for="defi" />
</div>
@endsection

@push('scripts')
@include('web.partials.countdown-js')
@endpush
