@extends('layouts.web')

@section('title', __('Tous les matchs en direct').' – '.config('terrain.app_name'))
@section('description', __('Regarde les matchs en direct avec :b sur :app.', ['b' => $p->name, 'app' => config('terrain.app_name')]))

@section('content')
<div class="page gut">
  <p class="meta" style="margin:0"><a href="{{ route('live') }}">← {{ __('En direct') }}</a></p>
  <div class="livehead">
    <div>
      <h1>{{ __('Tous les matchs en direct') }}</h1>
      <p class="pub-lead">{{ __('Avec :b, partenaire officiel de :app.', ['b' => $p->name, 'app' => config('terrain.app_name')]) }}@if ($p->note) {{ $p->note }}@endif</p>
    </div>
  </div>

  @if ($p->inside)
    @include('web.partials.stream-player', ['player' => $p->player, 'title' => $p->name, 'linkUrl' => $p->url, 'linkName' => $p->name])
    <p class="meta"><a href="{{ $p->url }}" target="_blank" rel="noopener">{{ __('Ouvrir dans le site de :b', ['b' => $p->name]) }}</a></p>
  @else
    <section class="watchbox">
      <h2>{{ __('Regarder en direct') }}</h2>
      <a class="btn live big" href="{{ $p->url }}" target="_blank" rel="noopener">● {{ __('Regarder sur :b', ['b' => $p->name]) }}</a>
      <p class="meta" style="margin:8px 0 0">{{ __('Le site du partenaire s’ouvre dans un nouvel onglet.') }}</p>
    </section>
  @endif

  @if ($live)
    <h2 style="margin-top:28px">{{ __('Scores en direct') }}</h2>
    <div class="tvlist">
      @foreach ($live as $m)
        @php
          [$lead, $txt] = \App\Terrain\Growth\ScoreChoice::text($m);
        @endphp
        <a class="tvrow" href="{{ route('matches.show', $m['id']) }}"><span><b>{{ $txt }}</b><small>{{ $lead }}@if (! empty($m['competition_name'] ?? $m['competition'] ?? '')) · {{ $m['competition_name'] ?? $m['competition'] }}@endif</small></span><span class="btn sm ghost">{{ __('Détails') }}</span></a>
      @endforeach
    </div>
  @endif
</div>
@endsection
