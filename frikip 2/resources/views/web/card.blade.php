@extends('layouts.web')

@section('title', __('Carte de joueur').' – '.$a['name'])

@push('meta')
<meta property="og:title" content="{{ $a['name'] }} – {{ $a['sport'] }}">
<meta property="og:image" content="{{ route('athletes.card.png', $a['id']) }}">
<meta property="og:url" content="{{ $url }}">
<meta name="twitter:card" content="summary_large_image">
@endpush

@php
  [$c1, $c2] = \App\Terrain\SportFields::color($a['sport']);
  $first = \Illuminate\Support\Arr::first($a['fields']) ?? $a['sport'];
  $stats = array_slice($a['fields'], 1, 3, true);
  $poster = $a['videos'][0]['poster'] ?? null;
  $cardData = [
      'name' => $a['name'], 'sport' => mb_strtoupper($a['sport']), 'line' => $first.' · '.$a['age'].' '.__('ans').' · '.$a['city'],
      'rating' => (string) round($rating), 'verified' => (bool) $a['verified'], 'stats' => $stats,
      'brand' => mb_strtoupper(config('terrain.app_name')), 'scan' => __('Scanne pour voir mes vidéos'),
  ];
@endphp

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:520px">
    <h1>{{ __('Carte de joueur') }}</h1>
    <p class="meta">{{ __('Télécharge l’image et mets-la en statut WhatsApp, sur TikTok ou Facebook. Le QR code mène à ton profil.') }}</p>

    <div class="pcard" id="pcard" style="--c1:{{ $c1 }};--c2:{{ $c2 }}">
      @if ($poster)<div class="pimg" style="background-image:url('{{ $poster }}')"></div>@endif
      <div class="ptop"><b class="prate">{{ round($rating) }}</b><span>{{ mb_strtoupper($a['sport']) }}</span>@if ($a['verified'])<span class="pver">✓ {{ __('VÉRIFIÉ') }}</span>@endif<i class="pbrand">{{ mb_strtoupper(config('terrain.app_name')) }}</i></div>
      <div class="pbody">
        <h2>{{ $a['name'] }}</h2>
        <p>{{ $first }} · {{ $a['age'] }} {{ __('ans') }} · {{ $a['city'] }}</p>
        <div class="pstats">@foreach ($stats as $k => $v)<div><b>{{ $v }}</b><span>{{ $k }}</span></div>@endforeach</div>
        <div class="pfoot"><div id="qr" class="pqr" aria-label="QR code vers le profil"></div><div><b>{{ __('Scanne pour voir mes vidéos') }}</b><span>{{ preg_replace('#^https?://#', '', $url) }}</span></div></div>
      </div>
    </div>

    <div class="row" style="gap:8px;flex-wrap:wrap;margin-top:14px">
      <button class="btn" type="button" id="dl">{{ __('Télécharger l’image') }}</button>
      <a class="btn wa" href="https://wa.me/?text={{ rawurlencode(__('Regarde mon profil de sportif').' : '.$url) }}" target="_blank" rel="noopener">{{ __('Envoyer sur WhatsApp') }}</a>
      <a class="btn ghost" href="{{ route('athletes.show', $a['id']) }}">{{ __('Voir le profil') }}</a>
    </div>
  </div>
  <x-fabara for="carte" />
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
  var url = @json($url);
  try { new QRCode(document.getElementById('qr'), { text: url, width: 96, height: 96, correctLevel: QRCode.CorrectLevel.M }); } catch (e) {}
  var a = @json($cardData);
  var c1 = @json($c1), c2 = @json($c2), poster = @json($poster);

  // Dessin de la carte en 1080 × 1350 (format portrait des statuts et publications)
  function draw(img) {
    var W = 1080, H = 1350, cv = document.createElement('canvas'); cv.width = W; cv.height = H;
    var g = cv.getContext('2d');
    var grd = g.createLinearGradient(0, 0, 0, H); grd.addColorStop(0, c1); grd.addColorStop(1, c2); g.fillStyle = grd; g.fillRect(0, 0, W, H);
    if (img) {
      var th = 780, tw = img.width * th / img.height; g.drawImage(img, (W - tw) / 2, 140, tw, th);
      var f = g.createLinearGradient(0, 620, 0, 920); f.addColorStop(0, 'rgba(0,0,0,0)'); f.addColorStop(1, c2); g.fillStyle = f; g.fillRect(0, 620, W, 300);
      g.fillStyle = c2; g.fillRect(0, 919, W, H - 919);
    }
    g.fillStyle = '#FFCD50'; g.font = '800 120px "Barlow Condensed", Arial Narrow, sans-serif'; g.fillText(a.rating, 60, 150);
    g.fillStyle = '#fff'; g.font = '700 36px Barlow, Arial, sans-serif'; g.fillText(a.sport, 64, 200);
    if (a.verified) g.fillText('✓ VÉRIFIÉ', 64, 250);
    g.globalAlpha = .7; g.textAlign = 'right'; g.fillText(a.brand, W - 60, 90); g.globalAlpha = 1;
    g.textAlign = 'center'; g.font = '800 ' + (a.name.length > 18 ? 76 : 92) + 'px "Barlow Condensed", Arial Narrow, sans-serif'; g.fillText(a.name.toUpperCase(), W / 2, 1000);
    g.globalAlpha = .85; g.font = '500 34px Barlow, Arial, sans-serif'; g.fillText(a.line, W / 2, 1050); g.globalAlpha = 1;
    var keys = Object.keys(a.stats), bw = 300;
    keys.forEach(function (k, i) {
      var x = 70 + i * 330; g.fillStyle = 'rgba(0,0,0,.28)'; g.fillRect(x, 1090, bw, 120);
      g.fillStyle = '#fff'; g.textAlign = 'left'; g.font = '700 34px Barlow, Arial, sans-serif'; g.fillText(String(a.stats[k]).slice(0, 16), x + 20, 1145);
      g.globalAlpha = .75; g.font = '500 22px Barlow, Arial, sans-serif'; g.fillText(k.toUpperCase().slice(0, 22), x + 20, 1185); g.globalAlpha = 1;
    });
    var q = document.querySelector('#qr canvas') || document.querySelector('#qr img');
    g.fillStyle = '#fff'; g.fillRect(70, 1225, 110, 110); if (q) g.drawImage(q, 77, 1232, 96, 96);
    g.textAlign = 'left'; g.font = '700 30px Barlow, Arial, sans-serif'; g.fillText(a.scan, 205, 1270);
    g.globalAlpha = .8; g.font = '500 26px Barlow, Arial, sans-serif'; g.fillText(url.replace(/^https?:\/\//, ''), 205, 1310); g.globalAlpha = 1;
    return cv;
  }
  document.getElementById('dl').onclick = function () {
    var go = function (img) {
      var cv = draw(img), link = document.createElement('a');
      link.download = 'carte-' + a.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.png';
      try { link.href = cv.toDataURL('image/png'); link.click(); }
      catch (e) { cv = draw(null); link.href = cv.toDataURL('image/png'); link.click(); }
    };
    if (!poster) return go(null);
    var img = new Image(); img.crossOrigin = 'anonymous'; img.onload = function () { go(img); }; img.onerror = function () { go(null); }; img.src = poster;
  };
})();
</script>
@endpush
