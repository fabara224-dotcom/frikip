@extends('layouts.web')

@section('title', $partner['name'].' – Messages')
@section('body-class', 'threadpage')

@section('content')
<div class="page gut thread">
  <div class="narrow" style="max-width:720px">
    <div class="thead">
      <a class="back" href="{{ route('messages') }}" aria-label="Retour aux messages">←</a>
      <a class="who" href="{{ str_starts_with($partnerId, 'r') ? '#' : route('athletes.show', $partnerId) }}">
        <x-av :name="$partner['name']" :sport="$partner['sport']" />
        <span style="min-width:0">
          <b>{{ $partner['name'] }} @if ($partner['verified'])<x-ver />@endif</b>
          <small class="meta" id="presence">{{ $partner['minor'] ? 'Sportif mineur · tuteur en copie' : ($partner['sport'] ?: 'Recruteur') }}</small>
        </span>
      </a>
      <div class="tactions">
        <button class="iconbtn" type="button" data-call="audio" aria-label="Appel vocal" title="{{ $canCall ? 'Appel vocal' : $callReason }}" @unless ($canCall) aria-disabled="true" @endunless>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
        </button>
        <button class="iconbtn" type="button" data-call="video" aria-label="Appel vidéo" title="{{ $canCall ? 'Appel vidéo' : $callReason }}" @unless ($canCall) aria-disabled="true" @endunless>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3"/></svg>
        </button>
        <details class="more">
          <summary class="iconbtn" aria-label="Plus d'options">⋯</summary>
          <form method="POST" action="{{ route('report') }}" class="card">@csrf
            <input type="hidden" name="target_type" value="message"><input type="hidden" name="target_id" value="{{ $partnerId }}">
            <label for="reason">Signaler cette personne<textarea id="reason" name="reason" rows="3" required placeholder="Que se passe-t-il ?"></textarea></label>
            <button class="btn ghost" type="submit">Envoyer le signalement</button>
          </form>
        </details>
      </div>
    </div>
    @if (! empty($blocked))<p class="note">{{ __($blocked) }}</p>@endif
    @if (($me['role'] ?? '') === 'athlete' && str_starts_with($partnerId, 'r-') && ! $partner['verified'])<p class="note">{{ __('Ce recruteur n’est pas encore vérifié par l’équipe. Ne paie jamais pour un essai et préviens-nous en cas de doute.') }}</p>@endif
    @if ($partner['minor'])<p class="note">Ce sportif est mineur : la conversation est visible par son tuteur, et les appels sont désactivés.</p>@endif

    <div class="chat" id="chat" aria-live="polite">
      <noscript>
        @foreach ($messages as $msg)
          <div class="bub {{ $msg['mine'] ? 'me' : '' }}">{{ $msg['text'] ?: ($msg['kind'] === 'voice' ? 'Message vocal' : 'Photo') }}<small>{{ $msg['at'] }}</small></div>
        @endforeach
      </noscript>
    </div>
    <p class="meta" id="empty" @if (count($messages)) hidden @endif>Présente-toi et explique ta proposition.</p>

    <form method="POST" action="{{ route('messages.store', $partnerId) }}" class="compose" id="compose">@csrf
      <button class="iconbtn" type="button" id="attach" aria-label="Envoyer une photo" title="Envoyer une photo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3.5"/><path d="M8 5l1.5-2h5L16 5"/></svg>
      </button>
      <input type="file" id="photo" accept="image/*" hidden>
      <input name="text" id="text" type="text" maxlength="2000" placeholder="Écrire un message…" aria-label="Message" autocomplete="off">
      <button class="btn round" type="submit" id="send" aria-label="Envoyer" hidden>
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 20l18-8L3 4v6l12 2-12 2z"/></svg>
      </button>
      <button class="btn round" type="button" id="mic" aria-label="Enregistrer un message vocal" title="Message vocal">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
      </button>
      <div class="recbar" id="recbar" hidden>
        <button class="iconbtn" type="button" id="rec-cancel" aria-label="Annuler le vocal">✕</button>
        <span class="recdot"></span><span id="rec-time">0:00</span><span class="meta grow">Enregistrement… (2 min max)</span>
        <button class="btn round" type="button" id="rec-send" aria-label="Envoyer le vocal"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 20l18-8L3 4v6l12 2-12 2z"/></svg></button>
      </div>
    </form>
    @error('text')<p class="field-error">{{ $message }}</p>@enderror
  </div>
</div>

<div class="lightbox" id="lightbox" hidden><img alt="Photo envoyée"><button class="x" type="button" aria-label="Fermer">×</button></div>
@endsection

@push('scripts')
<script>
(function () {
  var T = window.Terrain;
  var chat = document.getElementById('chat'), empty = document.getElementById('empty');
  var urls = {
    send: @json(route('messages.store', $partnerId)), upload: @json(route('messages.upload', $partnerId)),
    poll: @json(route('messages.poll', $partnerId)), call: @json(route('calls.start', $partnerId))
  };
  var canCall = @json($canCall), callReason = @json($callReason);
  var money = new RegExp(@json($moneyWords), 'i');
  var seen = {}, lastCursor = null, lastDay = null, readUpto = null;
  var today = new Date().toISOString().slice(0, 10), yesterday = new Date(Date.now() - 864e5).toISOString().slice(0, 10);

  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function mmss(s) { s = Math.max(0, Math.round(s || 0)); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); }
  function after(a, b) { return (/^\d+$/.test(a) && /^\d+$/.test(b)) ? Number(a) > Number(b) : a > b; }
  function dayLabel(d) {
    if (d === today) return "Aujourd'hui"; if (d === yesterday) return 'Hier';
    return new Date(d + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
  }

  function voicePlayer(m) {
    var w = el('div', 'voice'), b = el('button', 'vplay'), bar = el('div', 'vbar'), fill = el('i'), time = el('span', 'vtime', mmss(m.duration));
    b.type = 'button'; b.setAttribute('aria-label', 'Écouter le message vocal'); b.textContent = '▶';
    var a = new Audio(); a.preload = 'none'; a.src = m.media_url;
    b.onclick = function () {
      document.querySelectorAll('audio.playing').forEach(function () {});
      if (a.paused) { window.__voice && window.__voice !== a && window.__voice.pause(); window.__voice = a; a.play().catch(function () { T.toast('Lecture impossible'); }); } else a.pause();
    };
    a.onplay = function () { b.textContent = '❚❚'; }; a.onpause = a.onended = function () { b.textContent = '▶'; };
    a.ontimeupdate = function () { var d = a.duration && isFinite(a.duration) ? a.duration : (m.duration || 1); fill.style.width = Math.min(100, a.currentTime / d * 100) + '%'; time.textContent = mmss(a.currentTime); };
    a.onended = function () { b.textContent = '▶'; fill.style.width = '0'; time.textContent = mmss(m.duration); };
    bar.onclick = function (e) { var r = bar.getBoundingClientRect(), d = a.duration && isFinite(a.duration) ? a.duration : m.duration; if (d) { a.currentTime = (e.clientX - r.left) / r.width * d; if (a.paused) b.onclick(); } };
    bar.appendChild(fill); w.append(b, bar, time);
    return w;
  }

  function render(m) {
    if (seen[m.id]) { var old = seen[m.id]; if (m.read) markRead(old); return; }
    if (m.day !== lastDay) { chat.appendChild(el('div', 'dayline', dayLabel(m.day))); lastDay = m.day; }
    var node;
    if (m.kind === 'call') {
      node = el('div', 'callrow ' + (m.call && /missed|declined|cancelled/.test(m.call.status) && !m.call.outgoing ? 'missed' : ''));
      node.appendChild(el('span', null, (m.call && m.call.kind === 'video' ? '🎥 ' : '📞 ') + m.text + ' · ' + m.at));
      if (canCall) { var rb = el('button', 'linkbtn', 'Rappeler'); rb.type = 'button'; rb.dataset.call = m.call ? m.call.kind : 'audio'; node.appendChild(rb); }
    } else {
      node = el('div', 'bub ' + (m.mine ? 'me' : '') + (m.kind !== 'text' ? ' media' : ''));
      if (m.kind === 'voice') node.appendChild(m.media_url ? voicePlayer(m) : el('span', 'meta', 'Message vocal indisponible'));
      else if (m.kind === 'image') {
        if (m.media_url) { var img = el('img'); img.src = m.media_url; img.alt = 'Photo'; img.loading = 'lazy'; img.onclick = function () { openBox(m.media_url); }; node.appendChild(img); }
        else node.appendChild(el('span', 'meta', 'Photo indisponible'));
      } else node.appendChild(document.createTextNode(m.text));
      var meta = el('small', null, m.at);
      if (m.mine) { var tk = el('span', 'tick' + (m.read ? ' read' : ''), m.read ? ' ✓✓' : ' ✓'); tk.title = m.read ? 'Lu' : 'Envoyé'; meta.appendChild(tk); }
      node.appendChild(meta);
      node.dataset.cursor = m.cursor; if (m.mine) node.dataset.mine = '1';
    }
    chat.appendChild(node);
    seen[m.id] = node;
    if (!m.mine && m.kind === 'text' && money.test(m.text)) {
      chat.appendChild(el('p', 'note', "Attention : ce message parle d'argent. Ne payez jamais de frais d'essai, d'agent ou de visa. Utilisez « Signaler » (⋯ en haut)."));
    }
    if (!lastCursor || after(m.cursor, lastCursor)) lastCursor = m.cursor;
    empty.hidden = true;
  }
  function markRead(node) { var tk = node.querySelector('.tick'); if (tk && !tk.classList.contains('read')) { tk.classList.add('read'); tk.textContent = ' ✓✓'; tk.title = 'Lu'; } }
  function applyRead(upto) {
    if (!upto) return;
    chat.querySelectorAll('.bub[data-mine]').forEach(function (n) { if (!after(n.dataset.cursor, upto)) markRead(n); });
  }
  function bottom(force) {
    var near = window.innerHeight + window.scrollY > document.body.scrollHeight - 220;
    if (force || near) window.scrollTo(0, document.body.scrollHeight);
  }

  @json($messages).forEach(render);
  bottom(true);

  // ---- Nouveaux messages
  var busy = false;
  function poll() {
    if (busy) return; busy = true;
    fetch(urls.poll + (lastCursor ? '?after=' + encodeURIComponent(lastCursor) : ''), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        var had = chat.children.length;
        d.messages.forEach(render); applyRead(d.read_upto);
        if (chat.children.length !== had) bottom(false);
      }).catch(function () {}).then(function () { busy = false; });
  }
  setInterval(function () { if (!document.hidden) poll(); }, 2500);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

  // ---- Envoi d'un texte
  var form = document.getElementById('compose'), input = document.getElementById('text'), send = document.getElementById('send'), mic = document.getElementById('mic');
  function toggleSend() { var has = input.value.trim().length > 0; send.hidden = !has; mic.hidden = has; }
  input.addEventListener('input', toggleSend); toggleSend();
  function handle(r) {
    return r.json().catch(function () { return {}; }).then(function (d) {
      if (r.status === 402 && d.url) { location.href = d.url; throw 0; }
      if (r.status === 422) { T.toast((d.errors && Object.values(d.errors)[0][0]) || 'Envoi refusé'); throw 0; }
      if (!r.ok) { T.toast(d.error || 'Message non envoyé'); throw 0; }
      render(d.message); bottom(true); return d;
    });
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim(); if (!text) return;
    input.value = ''; toggleSend();
    fetch(urls.send, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': T.csrf }, body: JSON.stringify({ text: text }) })
      .then(handle).catch(function (x) { if (x !== 0) { T.toast('Pas de connexion : message non envoyé'); input.value = text; toggleSend(); } });
  });
  function upload(blob, name, kind, duration) {
    var fd = new FormData(); fd.append('file', blob, name); fd.append('kind', kind); if (duration) fd.append('duration', duration);
    var wait = el('div', 'bub me sending', kind === 'voice' ? 'Envoi du vocal…' : 'Envoi de la photo…'); chat.appendChild(wait); bottom(true);
    return fetch(urls.upload, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': T.csrf }, body: fd })
      .then(function (r) { wait.remove(); return handle(r); })
      .catch(function (x) { wait.remove(); if (x !== 0) T.toast('Pas de connexion : envoi impossible'); });
  }

  // ---- Photo (réduite à 1600 px pour économiser les données mobiles)
  var photo = document.getElementById('photo');
  document.getElementById('attach').onclick = function () { photo.click(); };
  photo.onchange = function () {
    var f = photo.files[0]; photo.value = ''; if (!f) return;
    var img = new Image(), url = URL.createObjectURL(f);
    img.onload = function () {
      var k = Math.min(1, 1600 / Math.max(img.width, img.height)), c = document.createElement('canvas');
      c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url);
      c.toBlob(function (b) { upload(b || f, 'photo.jpg', 'image'); }, 'image/jpeg', 0.82);
    };
    img.onerror = function () { URL.revokeObjectURL(url); upload(f, f.name, 'image'); };
    img.src = url;
  };

  // ---- Message vocal
  var recbar = document.getElementById('recbar'), recTime = document.getElementById('rec-time');
  var rec = null, chunks = [], started = 0, tick = null, stream = null, cancelled = false;
  function pickType() {
    var types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
    for (var i = 0; i < types.length; i++) if (window.MediaRecorder && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(types[i])) return types[i];
    return '';
  }
  function stopAll() { clearInterval(tick); recbar.hidden = true; form.classList.remove('recording'); if (stream) stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
  mic.onclick = function () {
    if (!window.isSecureContext || !navigator.mediaDevices || !window.MediaRecorder) {
      T.toast("Micro indisponible : ouvre le site en https (ou sur http://127.0.0.1 / localhost) avec un navigateur récent."); return;
    }
    navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }).then(function (s) {
      stream = s; chunks = []; cancelled = false;
      var type = pickType();
      rec = new MediaRecorder(s, type ? { mimeType: type, audioBitsPerSecond: 32000 } : undefined);
      rec.ondataavailable = function (e) { if (e.data.size) chunks.push(e.data); };
      rec.onstop = function () {
        var secs = Math.round((Date.now() - started) / 1000); stopAll();
        if (cancelled || secs < 1 || !chunks.length) return;
        var mime = (rec.mimeType || type || 'audio/webm').split(';')[0], ext = mime.indexOf('mp4') > -1 ? 'm4a' : mime.indexOf('ogg') > -1 ? 'ogg' : 'webm';
        upload(new Blob(chunks, { type: mime }), 'vocal.' + ext, 'voice', Math.min(300, secs));
      };
      rec.start(250); started = Date.now();
      recbar.hidden = false; form.classList.add('recording'); recTime.textContent = '0:00';
      tick = setInterval(function () { var s = (Date.now() - started) / 1000; recTime.textContent = mmss(s); if (s >= 120) rec.stop(); }, 250);
    }).catch(function () { T.toast("Accès au micro refusé. Autorise le micro dans les réglages du navigateur."); });
  };
  document.getElementById('rec-send').onclick = function () { if (rec && rec.state !== 'inactive') rec.stop(); };
  document.getElementById('rec-cancel').onclick = function () { cancelled = true; if (rec && rec.state !== 'inactive') rec.stop(); else stopAll(); };

  // ---- Appels
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-call]'); if (!b) return;
    if (!canCall) { T.toast(callReason); return; }
    b.disabled = true;
    fetch(urls.call, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': T.csrf }, body: JSON.stringify({ kind: b.dataset.call }) })
      .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw d; return d; }); })
      .then(function (d) { location.href = d.url; })
      .catch(function (d) { b.disabled = false; T.toast((d && d.error) || "L'appel n'a pas pu démarrer"); });
  });

  // ---- Photo en grand
  var box = document.getElementById('lightbox');
  function openBox(src) { box.querySelector('img').src = src; box.hidden = false; }
  box.onclick = function () { box.hidden = true; };
})();
</script>
@endpush
