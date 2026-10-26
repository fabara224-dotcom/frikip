{{-- Lecteur d'un flux partenaire : $player (Live::embed), $title, $linkUrl et $linkName pour le bouton quand le lien ne s'intègre pas. --}}
<div class="player watch-player">
  @if ($player['type'] === 'iframe')
    <iframe src="{{ $player['src'] }}" title="{{ $title }}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
  @elseif (in_array($player['type'], ['hls', 'video'], true))
    <video class="pstream" controls playsinline muted data-src="{{ $player['src'] }}" data-type="{{ $player['type'] }}"></video>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.5.13/hls.min.js"></script>
    <script>(function () { document.querySelectorAll('video.pstream').forEach(function (v) { if (v._on) return; v._on = 1; var s = v.dataset.src;
      if (v.dataset.type === 'hls' && !v.canPlayType('application/vnd.apple.mpegurl') && window.Hls && Hls.isSupported()) { var h = new Hls(); h.loadSource(s); h.attachMedia(v); } else { v.src = s; } }); })();</script>
  @else
    <a class="player-msg" href="{{ $linkUrl }}" target="_blank" rel="noopener"><span class="btn">{{ __('Regarder sur :b', ['b' => $linkName]) }}</span></a>
  @endif
</div>
