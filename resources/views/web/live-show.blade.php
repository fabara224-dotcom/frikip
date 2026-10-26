@extends('layouts.web')

@section('title', \App\Terrain\Growth\Live::label($s).' · '.__('En direct').' – '.config('terrain.app_name'))

@push('meta')
<meta property="og:title" content="{{ \App\Terrain\Growth\Live::label($s) }} · {{ __('En direct sur :app', ['app' => config('terrain.app_name')]) }}">
@endpush

@section('content')
<div class="page gut">
  <div class="liveshow">
    <div class="player" id="player" data-type="{{ $player['type'] }}">
      @if ($player['type'] === 'iframe')
        <iframe id="stream" @if ($preroll) data-src="{{ $player['src'] }}" @else src="{{ $player['src'] }}" @endif title="{{ $s->title }}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
      @elseif ($player['type'] === 'hls' || $player['type'] === 'video')
        <video id="stream" controls playsinline muted data-src="{{ $player['src'] }}"></video>
      @else
        <div class="player-msg">
          <p>{{ $s->status === 'prevu' ? __('Le direct n’a pas encore commencé.') : __('Ce direct s’ouvre sur le site du diffuseur.') }}</p>
          @if ($player['src'])<a class="btn" href="{{ $player['src'] }}" target="_blank" rel="noopener">{{ __('Regarder le match') }}</a>@endif
        </div>
      @endif

      @if ($preroll)
        <div class="preroll" id="preroll" data-ad="{{ $preroll['id'] }}">
          <video src="{{ $preroll['video'] }}" @if ($preroll['poster'])poster="{{ $preroll['poster'] }}" @endif autoplay muted playsinline></video>
          <div class="pr-top"><span class="adtag">{{ __('Publicité') }} · {{ $preroll['advertiser'] }}</span>
            <button type="button" id="pr-skip" disabled>{{ __('Passer dans') }} <b>5</b></button></div>
          <div class="pr-bot">
            @if ($preroll['link'])<a class="btn sm" href="{{ $preroll['link'] }}" target="_blank" rel="noopener sponsored">{{ __($preroll['cta']) }}</a>@endif
            <a class="pr-nopub" href="{{ route('premium', 'sans_pub') }}">{{ __('Retirer les pubs') }}</a>
          </div>
        </div>
      @endif
    </div>

    <div class="scorebar">
      <span class="livebadge {{ $s->status === 'direct' ? '' : 'off' }}" id="lv-status"><i aria-hidden="true"></i><span>{{ __(\App\Terrain\Growth\Live::STATUS[$s->status]) }}</span><span id="lv-min">@if ($s->minute) · {{ $s->minute }}@endif</span></span>
      @if (\App\Terrain\Growth\Live::hasScore($s))
        <div class="lscore big"><b>{{ $s->home }}</b><em><span id="lv-h">{{ $s->home_score }}</span> – <span id="lv-a">{{ $s->away_score }}</span></em><b>{{ $s->away }}</b></div>
      @else
        <div class="ltitle">{{ \App\Terrain\Growth\Live::label($s) }}</div>
      @endif
      <span class="meta" id="lv-viewers" @if ($s->status !== 'direct') hidden @endif>👁 <span>{{ $viewers }}</span> {{ __('spectateurs') }}</span>
    </div>
    <h1 class="livetitle">{{ $s->title }}</h1>
    <p class="meta">@td($s->sport)@if ($s->competition) · {{ $s->competition }}@endif @if ($s->city) · {{ $s->city }}@endif · {{ \App\Terrain\DataLang::t((string) $s->country) }}
      @if ($s->starts_at) · {{ \Illuminate\Support\Carbon::parse($s->starts_at)->translatedFormat('D j M · H\hi') }}@endif
      @if ($s->owner_name) · {{ __('Diffusé par :n', ['n' => $s->owner_name]) }}@endif</p>
    @if (($s->source ?? '') === 'youtube')<p class="meta" style="margin-top:-6px">{{ __('Direct diffusé sur YouTube par la chaîne :c, intégré avec son autorisation.', ['c' => $s->channel]) }}</p>@endif
    <div class="row" style="gap:8px;flex-wrap:wrap">
      <a class="btn ghost sm" href="https://wa.me/?text={{ rawurlencode(\App\Terrain\Growth\Live::label($s).' en direct : '.route('live.show', $s->id)) }}" target="_blank" rel="noopener">{{ __('Partager sur WhatsApp') }}</a>
      @if ($owner)<a class="btn sm" href="{{ route('live.manage', $s->id) }}">{{ __('Gérer le score') }}</a>@endif
    </div>

    @if ($others->count())
      <h2>{{ __('Autres matchs en direct') }}</h2>
      <div class="livelist">
        @foreach ($others as $o)
          <a class="listrow" href="{{ route('live.show', $o->id) }}"><span><b>{{ \App\Terrain\Growth\Live::label($o) }}</b><small>@td($o->sport) · {{ \App\Terrain\DataLang::t((string) $o->country) }}</small></span><span class="livebadge"><i></i>{{ __('En direct') }}</span></a>
        @endforeach
      </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
@if ($player['type'] === 'hls')
<script src="https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.5.13/hls.min.js"></script>
@endif
<script>
(function () {
  var type = @json($player['type']), stream = document.getElementById('stream');
  var adUrl = @json(route('ads.seen', '__ID__')), csrf = document.querySelector('meta[name=csrf-token]').content;

  function startStream() {
    if (!stream) return;
    if (type === 'iframe') { if (stream.dataset.src) stream.src = stream.dataset.src; return; }
    var src = stream.dataset.src;
    if (type === 'hls' && !stream.canPlayType('application/vnd.apple.mpegurl') && window.Hls && Hls.isSupported()) {
      var h = new Hls({ lowLatencyMode: true }); h.loadSource(src); h.attachMedia(stream);
    } else { stream.src = src; }
    stream.play().catch(function () {});
  }

  // Pub avant le match : passable après 5 secondes
  var pr = document.getElementById('preroll');
  if (pr) {
    var v = pr.querySelector('video'), skip = document.getElementById('pr-skip'), n = 5, done = false;
    fetch(adUrl.replace('__ID__', pr.dataset.ad), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } }).catch(function () {});
    var end = function () { if (done) return; done = true; pr.remove(); startStream(); };
    var t = setInterval(function () {
      n--; if (n > 0) { skip.querySelector('b').textContent = n; return; }
      clearInterval(t); skip.disabled = false; skip.textContent = @json(__('Passer la pub'));
    }, 1000);
    skip.addEventListener('click', end);
    v.addEventListener('ended', end);
    v.addEventListener('error', end);
  } else { startStream(); }

  // Score et spectateurs en direct
  var url = @json(route('live.state', $s->id)), $ = function (id) { return document.getElementById(id); };
  function refresh() {
    fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      if ($('lv-h')) { $('lv-h').textContent = d.home; $('lv-a').textContent = d.away; }
      $('lv-min').textContent = d.minute ? ' · ' + d.minute : '';
      $('lv-status').querySelector('span').textContent = d.label;
      $('lv-status').classList.toggle('off', d.status !== 'direct');
      $('lv-viewers').hidden = d.status !== 'direct'; $('lv-viewers').querySelector('span').textContent = d.viewers;
    }).catch(function () {});
  }
  refresh(); setInterval(refresh, 20000);
})();
</script>
@endpush
