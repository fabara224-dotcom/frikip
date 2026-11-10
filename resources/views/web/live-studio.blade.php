@extends('layouts.web')

@section('title', __('Mon live').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="liveshow">
    <div class="player studio">
      <video id="cam" autoplay muted playsinline></video>
      <span class="livebadge off" id="st-badge"><i aria-hidden="true"></i><span id="st-text">{{ __('Prêt') }}</span></span>
      <p class="player-msg" id="st-msg" hidden></p>
    </div>
    <div class="formbar studio-bar">
      <button class="btn live big" type="button" id="st-go" disabled>● {{ __('Démarrer le live') }}</button>
      <button class="btn ghost" type="button" id="st-flip">{{ __('Changer de caméra') }}</button>
      <button class="btn ghost" type="button" id="st-mute" aria-pressed="false">{{ __('Couper le micro') }}</button>
      <button class="btn ghost" type="button" id="st-stop" hidden>■ {{ __('Arrêter') }}</button>
    </div>
    <h1 class="livetitle">{{ $s->title }}</h1>
    <p class="meta">{{ __('Lien à partager') }} : <a href="{{ route('live.show', $s->id) }}">{{ route('live.show', $s->id) }}</a>
      · <a href="https://wa.me/?text={{ rawurlencode($s->title.' – en direct sur Frikip : '.route('live.show', $s->id)) }}" target="_blank" rel="noopener">{{ __('Partager sur WhatsApp') }}</a></p>
    <p class="note">{{ __('Rappel : ne filme jamais une télé ou un écran qui diffuse un match. Garde ton téléphone branché et en Wi-Fi si possible.') }}</p>
    @include('web.partials.room', ['room' => 'l-'.$s->id, 'roomTitle' => __('Commentaires de ton live')])
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/frikip-live.js') }}?v=1"></script>
<script>
(function () {
  var video = document.getElementById('cam'), go = document.getElementById('st-go'), stop = document.getElementById('st-stop');
  var flip = document.getElementById('st-flip'), mute = document.getElementById('st-mute'), msg = document.getElementById('st-msg');
  var badge = document.getElementById('st-badge'), txt = document.getElementById('st-text');
  var whipUrl = @json($s->whip_url), stateUrl = @json(route('live.studio.state', $s->id)), T = window.Terrain;
  var L = { live: @json(__('En direct')), starting: @json(__('Connexion…')), noCam: @json(__('Autorise la caméra et le micro pour lancer ton live (réglages du navigateur).')), fail: @json(__('Le live n’a pas pu démarrer. Vérifie ta connexion et réessaie.')), unmute: @json(__('Remettre le micro')), muteL: @json(__('Couper le micro')) };
  var stream = null, facing = 'user', session = null;
  async function cam() {
    if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: { echoCancellation: true, noiseSuppression: true } });
      video.srcObject = stream; go.disabled = false; msg.hidden = true;
      video.style.transform = facing === 'user' ? 'scaleX(-1)' : 'none';
    } catch (e) { msg.textContent = L.noCam; msg.hidden = false; go.disabled = true; }
  }
  function state(action) {
    return fetch(stateUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': T.csrf, Accept: 'application/json' }, body: JSON.stringify({ action: action }) }).then(function (r) { return r.json(); });
  }
  go.addEventListener('click', async function () {
    go.disabled = true; txt.textContent = L.starting;
    try {
      session = await window.FrikipLive.whip(whipUrl, stream);
      await state('direct');
      badge.classList.remove('off'); txt.textContent = L.live; go.hidden = true; stop.hidden = false; flip.disabled = true;
    } catch (e) { msg.textContent = L.fail; msg.hidden = false; go.disabled = false; txt.textContent = ''; }
  });
  stop.addEventListener('click', function () {
    if (session) session.stop(); if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
    state('stop').then(function (d) { location.href = d.url; });
  });
  flip.addEventListener('click', function () { facing = facing === 'user' ? 'environment' : 'user'; cam(); });
  mute.addEventListener('click', function () {
    if (!stream) return; var a = stream.getAudioTracks()[0]; if (!a) return; a.enabled = !a.enabled;
    mute.setAttribute('aria-pressed', a.enabled ? 'false' : 'true'); mute.textContent = a.enabled ? L.muteL : L.unmute;
  });
  window.addEventListener('beforeunload', function () { if (session) navigator.sendBeacon && navigator.sendBeacon(stateUrl, new Blob([JSON.stringify({ action: 'stop', _token: T.csrf })], { type: 'application/json' })); });
  cam();
})();
</script>
@endpush
