@extends('layouts.web')

@section('title', ($call['kind'] === 'video' ? 'Appel vidéo' : 'Appel vocal').' – '.$call['partner']['name'])
@section('body-class', 'callpage')
@section('fullbleed', '1')

@section('content')
<div class="callscreen {{ $call['kind'] }}" id="callscreen">
  <video id="remote" autoplay playsinline></video>
  <audio id="remote-audio" autoplay></audio>
  <video id="local" autoplay playsinline muted @if ($call['kind'] !== 'video') hidden @endif></video>

  <div class="callinfo" id="callinfo">
    <x-av :name="$call['partner']['name']" :sport="$call['partner']['sport']" lg />
    <h1>{{ $call['partner']['name'] }}</h1>
    <p id="cstatus">{{ $call['outgoing'] ? 'Appel en cours…' : ($call['kind'] === 'video' ? 'Appel vidéo entrant' : 'Appel vocal entrant') }}</p>
    <p class="ctimer" id="ctimer" hidden>0:00</p>
  </div>

  <div class="callbar" id="incoming" @if ($call['outgoing'] || $call['status'] !== 'ringing') hidden @endif>
    <button class="cbtn red" type="button" id="decline" aria-label="Refuser"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 9c-2.3 0-4.5.5-6.5 1.4-.6.3-1 .9-1 1.6v2.5c0 .8.8 1.4 1.6 1.2l3-.8c.6-.2 1-.7 1-1.3v-1.4a9 9 0 0 1 3.8 0v1.4c0 .6.4 1.1 1 1.3l3 .8c.8.2 1.6-.4 1.6-1.2V12c0-.7-.4-1.3-1-1.6A15 15 0 0 0 12 9z"/></svg><span>Refuser</span></button>
    <button class="cbtn green" type="button" id="accept" aria-label="Décrocher"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg><span>Décrocher</span></button>
  </div>

  <div class="callbar" id="controls" @if (! $call['outgoing'] && $call['status'] === 'ringing') hidden @endif>
    <button class="cbtn" type="button" id="mute" aria-pressed="false" aria-label="Couper le micro"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg><span>Micro</span></button>
    @if ($call['kind'] === 'video')
      <button class="cbtn" type="button" id="cam" aria-pressed="false" aria-label="Couper la caméra"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3"/></svg><span>Caméra</span></button>
      <button class="cbtn" type="button" id="flip" aria-label="Changer de caméra"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h11l-3-3M20 17H9l3 3"/></svg><span>Retourner</span></button>
    @endif
    <button class="cbtn red" type="button" id="hangup" aria-label="Raccrocher"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 9c-2.3 0-4.5.5-6.5 1.4-.6.3-1 .9-1 1.6v2.5c0 .8.8 1.4 1.6 1.2l3-.8c.6-.2 1-.7 1-1.3v-1.4a9 9 0 0 1 3.8 0v1.4c0 .6.4 1.1 1 1.3l3 .8c.8.2 1.6-.4 1.6-1.2V12c0-.7-.4-1.3-1-1.6A15 15 0 0 0 12 9z"/></svg><span>Raccrocher</span></button>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var T = window.Terrain;
  var call = @json($call);
  var urls = {
    state: @json(route('calls.state', $call['id'])), signal: @json(route('calls.signal', $call['id'])),
    status: @json(route('calls.status', $call['id'])), back: @json(route('messages.show', $call['partner']['id']))
  };
  var iceServers = @json($iceServers);
  var $ = function (id) { return document.getElementById(id); };
  var statusEl = $('cstatus'), timerEl = $('ctimer'), screen = $('callscreen');
  var pc = null, local = null, lastSignal = null, remoteSet = false, pendingIce = [], outIce = [], iceTimer = null;
  var finished = false, connectedAt = null, pollTimer = null, facing = 'user', ring = null;
  var video = call.kind === 'video';

  function setStatus(t) { statusEl.textContent = t; }
  function post(url, body) { return T.post(url, body); }

  // ---- Sonnerie (bip doux, sans fichier)
  function startRing(outgoing) {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var beep = function () {
        var o = ctx.createOscillator(), g = ctx.createGain(); o.frequency.value = outgoing ? 425 : 660; g.gain.value = 0.06;
        o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + (outgoing ? 1 : 0.4));
      };
      beep(); ring = { ctx: ctx, t: setInterval(beep, outgoing ? 3000 : 1200) };
    } catch (e) {}
  }
  function stopRing() { if (ring) { clearInterval(ring.t); try { ring.ctx.close(); } catch (e) {} ring = null; } }

  function secure() {
    if (window.isSecureContext && navigator.mediaDevices && window.RTCPeerConnection) return true;
    setStatus("Les appels demandent une adresse sécurisée : https://… (ou http://127.0.0.1 / localhost sur l'ordinateur du site).");
    return false;
  }

  function getMedia() {
    var want = { audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, video: video ? { facingMode: facing, width: { ideal: 640 }, height: { ideal: 480 } } : false };
    return navigator.mediaDevices.getUserMedia(want).catch(function () {
      if (!video) throw new Error('micro');
      video = false; $('local').hidden = true; T.toast('Caméra indisponible : appel en audio seulement');
      return navigator.mediaDevices.getUserMedia({ audio: true });
    }).then(function (s) { local = s; if (video) $('local').srcObject = s; return s; });
  }

  function makePc() {
    pc = new RTCPeerConnection({ iceServers: iceServers });
    local.getTracks().forEach(function (t) { pc.addTrack(t, local); });
    pc.ontrack = function (e) {
      var s = e.streams[0];
      if (e.track.kind === 'video') { $('remote').srcObject = s; screen.classList.add('has-remote'); }
      $('remote-audio').srcObject = s;
    };
    pc.onicecandidate = function (e) {
      if (!e.candidate) return;
      outIce.push(e.candidate.toJSON ? e.candidate.toJSON() : e.candidate);
      clearTimeout(iceTimer); iceTimer = setTimeout(flushIce, 250);
    };
    pc.onconnectionstatechange = function () {
      var st = pc.connectionState;
      if (st === 'connected') { onConnected(); setStatus(video ? '' : 'En communication'); }
      else if (st === 'disconnected') setStatus('Connexion instable…');
      else if (st === 'failed') { setStatus("Connexion impossible : le réseau bloque l'appel. (L'administrateur peut ajouter un serveur TURN.)"); setTimeout(function () { hangup('ended'); }, 4000); }
    };
    pc.oniceconnectionstatechange = function () { if (pc.iceConnectionState === 'connected' || pc.iceConnectionState === 'completed') onConnected(); };
  }
  function flushIce() {
    if (!outIce.length) return;
    var batch = outIce; outIce = [];
    post(urls.signal, { type: 'ice', payload: JSON.stringify(batch) }).catch(function () {});
  }
  function onConnected() {
    if (connectedAt) return;
    connectedAt = Date.now(); stopRing(); setStatus(video ? '' : 'En communication'); timerEl.hidden = false;
    screen.classList.add('connected');
    setInterval(function () { var s = Math.round((Date.now() - connectedAt) / 1000); timerEl.textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); }, 1000);
    schedule(2500);
  }

  function handleSignal(s) {
    var data = JSON.parse(s.payload);
    if (s.type === 'offer' && !call.outgoing && pc && !remoteSet) {
      pc.setRemoteDescription(data).then(function () { remoteSet = true; drainIce(); return pc.createAnswer(); })
        .then(function (a) { return pc.setLocalDescription(a); })
        .then(function () { return post(urls.signal, { type: 'answer', payload: JSON.stringify(pc.localDescription) }); })
        .catch(function () { setStatus('Erreur de connexion'); });
    } else if (s.type === 'answer' && call.outgoing && pc && !remoteSet) {
      pc.setRemoteDescription(data).then(function () { remoteSet = true; drainIce(); if (!connectedAt) setStatus('Connexion…'); });
    } else if (s.type === 'ice') {
      (Array.isArray(data) ? data : [data]).forEach(function (c) { if (remoteSet && pc) pc.addIceCandidate(c).catch(function () {}); else pendingIce.push(c); });
    }
  }
  function drainIce() { pendingIce.splice(0).forEach(function (c) { pc.addIceCandidate(c).catch(function () {}); }); }

  var ENDED = { declined: 'Appel refusé', missed: call.outgoing ? (call.fictional ? 'Pas de réponse (profil fictif de démo)' : 'Pas de réponse') : 'Appel manqué', ended: 'Appel terminé', cancelled: 'Appel annulé' };
  function poll() {
    fetch(urls.state + (lastSignal ? '?after=' + lastSignal : ''), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        if (ENDED[d.call.status]) return finish(ENDED[d.call.status]);
        if (d.call.status === 'accepted' && call.outgoing && !connectedAt && !remoteSet) setStatus('Connexion…');
        d.signals.forEach(function (s) { lastSignal = s.id; handleSignal(s); });
      }).catch(function () {}).then(function () { if (!finished) schedule(connectedAt ? 2500 : 1000); });
  }
  function schedule(ms) { clearTimeout(pollTimer); pollTimer = setTimeout(poll, ms); }

  function finish(text) {
    if (finished) return; finished = true;
    stopRing(); clearTimeout(pollTimer); setStatus(text); timerEl.hidden = !connectedAt;
    screen.classList.add('ended'); $('incoming').hidden = true; $('controls').hidden = true;
    if (pc) pc.close(); if (local) local.getTracks().forEach(function (t) { t.stop(); });
    setTimeout(function () { location.href = urls.back; }, 2200);
  }
  function hangup(status) {
    if (finished) return;
    post(urls.status, { status: status }).catch(function () {});
    finish(status === 'declined' ? 'Appel refusé' : 'Appel terminé');
  }

  // ---- Appelant : on prépare l'offre tout de suite
  function startOutgoing() {
    if (!secure()) return;
    startRing(true);
    getMedia().then(function () {
      makePc();
      return pc.createOffer();
    }).then(function (o) { return pc.setLocalDescription(o); })
      .then(function () { return post(urls.signal, { type: 'offer', payload: JSON.stringify(pc.localDescription) }); })
      .then(function () { setStatus('Ça sonne…'); schedule(500); })
      .catch(function () { setStatus('Micro ou caméra refusé : autorise-les dans le navigateur.'); post(urls.status, { status: 'cancelled' }).catch(function () {}); setTimeout(function () { location.href = urls.back; }, 3500); });
  }
  // ---- Appelé : on décroche puis on répond à l'offre
  function accept() {
    if (!secure()) return;
    stopRing(); $('incoming').hidden = true; $('controls').hidden = false; setStatus('Connexion…');
    getMedia().then(function () {
      makePc();
      return post(urls.status, { status: 'accepted' });
    }).then(function (d) {
      if (ENDED[d.call.status]) return finish(ENDED[d.call.status]);
      lastSignal = null; schedule(0);
    }).catch(function () { setStatus('Micro refusé : autorise-le dans le navigateur.'); hangup('declined'); });
  }

  $('hangup').onclick = function () { hangup('ended'); };
  if ($('accept')) $('accept').onclick = accept;
  if ($('decline')) $('decline').onclick = function () { hangup('declined'); };
  $('mute').onclick = function () {
    if (!local) return; var on = this.getAttribute('aria-pressed') !== 'true';
    local.getAudioTracks().forEach(function (t) { t.enabled = !on; }); this.setAttribute('aria-pressed', on ? 'true' : 'false');
  };
  if ($('cam')) $('cam').onclick = function () {
    if (!local) return; var on = this.getAttribute('aria-pressed') !== 'true';
    local.getVideoTracks().forEach(function (t) { t.enabled = !on; }); this.setAttribute('aria-pressed', on ? 'true' : 'false');
  };
  if ($('flip')) $('flip').onclick = function () {
    if (!local || !pc) return;
    facing = facing === 'user' ? 'environment' : 'user';
    navigator.mediaDevices.getUserMedia({ video: { facingMode: facing } }).then(function (s) {
      var nt = s.getVideoTracks()[0], sender = pc.getSenders().find(function (x) { return x.track && x.track.kind === 'video'; });
      if (sender) sender.replaceTrack(nt);
      local.getVideoTracks().forEach(function (t) { t.stop(); local.removeTrack(t); }); local.addTrack(nt); $('local').srcObject = local;
    }).catch(function () { T.toast('Une seule caméra disponible'); });
  };
  // Fermer la page = raccrocher
  window.addEventListener('pagehide', function () {
    if (finished) return;
    var fd = new FormData(); fd.append('_token', T.csrf); fd.append('status', call.outgoing || connectedAt ? 'ended' : 'declined');
    navigator.sendBeacon && navigator.sendBeacon(urls.status, fd);
  });

  if (call.status !== 'ringing' && call.status !== 'accepted') finish(ENDED[call.status] || 'Appel terminé');
  else if (call.outgoing) startOutgoing();
  else { startRing(false); schedule(1000); }   // l'appelé touche « Décrocher » (les navigateurs exigent un geste pour le micro)
})();
</script>
@endpush
