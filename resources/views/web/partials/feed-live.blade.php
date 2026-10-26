{{-- Match en direct au milieu du fil : le lecteur démarre sans le son quand la carte est à l'écran. --}}
@php
  $emb = \App\Terrain\Growth\Live::embed($s->stream_url);
  $score = \App\Terrain\Growth\Live::hasScore($s);
@endphp
<section class="tki liveslot" data-live="{{ $s->id }}">
  <div class="vid" style="background:#0B2318">
    @if ($emb['type'] === 'iframe')
      <iframe data-src="{{ str_replace('mute=1', 'mute=1&controls=0', $emb['src']) }}" title="{{ $s->title }}" allow="autoplay; encrypted-media; picture-in-picture" loading="lazy"></iframe>
    @elseif ($s->thumbnail)
      <img src="{{ $s->thumbnail }}" alt="" loading="lazy">
    @endif
    <a class="live-open" href="{{ route('live.show', $s->id) }}" aria-label="{{ __('Regarder le match') }}"></a>
    <div class="shade"></div>
    <div class="tmeta">
      <span class="livebadge"><i aria-hidden="true"></i>{{ __('En direct') }}@if ($s->minute) · {{ $s->minute }}@endif</span>
      @if ($score)
        <div class="lscore"><b>{{ $s->home }}</b><em>{{ $s->home_score }} – {{ $s->away_score }}</em><b>{{ $s->away }}</b></div>
      @else
        <div class="who">{{ \App\Terrain\Growth\Live::label($s) }}</div>
      @endif
      <div class="spt">@td($s->sport)@if ($s->competition) · {{ $s->competition }}@endif @if ($s->channel) · {{ $s->channel }}@endif</div>
      <a class="btn adcta" href="{{ route('live.show', $s->id) }}">{{ __('Regarder le match') }}</a>
    </div>
  </div>
  <div class="tkbar"><a href="{{ route('live') }}">{{ __('Tous les matchs en direct') }}</a></div>
</section>
