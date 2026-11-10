@extends('layouts.web')

@section('title', __('Publier une vidéo').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow pub">
    <h1>{{ __('Publier une vidéo') }}</h1>
    <p class="pub-lead">{{ __('Montre tes meilleures actions. Une vidéo courte et verticale attire plus de recruteurs.') }}</p>

    <form method="POST" action="{{ route('publish') }}" enctype="multipart/form-data" id="pub" novalidate>@csrf
      <input class="visually-hidden" id="video" name="video" type="file" accept="video/*,.mp4,.mov,.webm,.m4v">
      <input type="hidden" name="duration" id="duration">
      <input type="hidden" name="edit" id="edit">

      {{-- 1. Choisir la vidéo --}}
      <label for="video" class="drop" id="drop">
        <span class="drop-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M6.5 9.5 12 4l5.5 5.5"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg></span>
        <b>{{ __('Choisir ma vidéo') }}</b>
        <span>{{ __('Depuis ta galerie ou ta caméra. MP4 ou MOV, jusqu’à :n Mo.', ['n' => $maxMb]) }}</span>
      </label>
      <button class="btn ghost filmbtn" type="button" id="film">🎥 {{ __('Filmer maintenant avec un filtre') }}</button>
      <div class="picked" id="picked" hidden>
        <div class="picked-info">
          <b id="fname"></b>
          <span class="meta" id="fsize"></span>
          <label for="video" class="btn ghost sm">{{ __('Changer de vidéo') }}</label>
        </div>
      </div>
      {{-- Éditeur : filtres, coupe, vitesse, texte, son (comme sur TikTok) --}}
      <div class="editor" id="editor" hidden>
        <div class="edstage">
          <video id="preview" muted playsinline loop preload="metadata"></video>
          <div class="cap edcap" id="edcap" hidden></div>
          <button class="edplay" type="button" id="edplay" aria-label="{{ __('Lecture / pause') }}">❚❚</button>
        </div>
        <div class="edtabs" role="tablist" aria-label="{{ __('Modifier la vidéo') }}">
          <button type="button" role="tab" aria-selected="true" data-tab="filtres">🎨 {{ __('Filtres') }}</button>
          <button type="button" role="tab" aria-selected="false" data-tab="couper">✂️ {{ __('Couper') }}</button>
          <button type="button" role="tab" aria-selected="false" data-tab="vitesse">⏩ {{ __('Vitesse') }}</button>
          <button type="button" role="tab" aria-selected="false" data-tab="texte">🔤 {{ __('Texte') }}</button>
          <button type="button" role="tab" aria-selected="false" data-tab="son">🔊 {{ __('Son') }}</button>
        </div>
        <div class="edpanel" data-panel="filtres">
          <div class="edfilters">
            @foreach (\App\Terrain\Growth\VideoEditor::FILTERS as $k => [$label, $css])
              <button type="button" class="edf" data-filter="{{ $k }}" data-css="{{ $css }}" aria-pressed="{{ $k === 'normal' ? 'true' : 'false' }}"><i style="filter:{{ $css }}"></i>{{ __($label) }}</button>
            @endforeach
          </div>
        </div>
        <div class="edpanel" data-panel="couper" hidden>
          <p class="meta" style="margin:0">{{ __('Garde seulement ta meilleure action : déplace le début et la fin.') }}</p>
          <label for="ed-start">{{ __('Début') }} <b id="ed-start-l">0:00</b><input type="range" id="ed-start" min="0" max="0" step="0.1" value="0"></label>
          <label for="ed-end">{{ __('Fin') }} <b id="ed-end-l">0:00</b><input type="range" id="ed-end" min="0" max="0" step="0.1" value="0"></label>
          <p class="meta" style="margin:0" id="ed-len"></p>
        </div>
        <div class="edpanel" data-panel="vitesse" hidden>
          <div class="chips pickchips">
            @foreach (\App\Terrain\Growth\VideoEditor::SPEEDS as $sp)
              <label class="chip"><input type="radio" name="ed_speed" value="{{ $sp }}" @checked($sp == 1)> {{ $sp == 0.5 ? __('Ralenti ×0,5') : '×'.str_replace('.', ',', (string) $sp) }}</label>
            @endforeach
          </div>
          <p class="meta" style="margin:0">{{ __('Le ralenti montre bien un geste technique ; ×1,5 donne du rythme à une compilation.') }}</p>
        </div>
        <div class="edpanel" data-panel="texte" hidden>
          <label for="caption">{{ __('Texte affiché sur la vidéo') }}<input id="caption" name="caption" maxlength="120" value="{{ old('caption') }}" placeholder="{{ __('Ex. : 14 buts cette saison, regardez le n°7') }}"></label>
          <b class="sflabel">{{ __('Position') }}</b>
          <div class="chips pickchips">
            @foreach (['haut' => __('En haut'), 'milieu' => __('Au milieu'), 'bas' => __('En bas')] as $k => $l)<label class="chip"><input type="radio" name="ed_pos" value="{{ $k }}" @checked($k === 'milieu')> {{ $l }}</label>@endforeach
          </div>
          <b class="sflabel">{{ __('Couleur') }}</b>
          <div class="chips pickchips">
            @foreach (['blanc' => __('Blanc'), 'jaune' => __('Jaune'), 'vert' => __('Vert')] as $k => $l)<label class="chip"><input type="radio" name="ed_color" value="{{ $k }}" @checked($k === 'blanc')> {{ $l }}</label>@endforeach
          </div>
        </div>
        <div class="edpanel" data-panel="son" hidden>
          <label class="check"><input type="checkbox" id="ed-mute"> {{ __('Couper le son de la vidéo (bruit, vent, musique protégée)') }}</label>
          <p class="meta" style="margin:0">{{ __('Évite les musiques connues : elles sont protégées par des droits d’auteur.') }}</p>
        </div>
      </div>

      {{-- Caméra : filmer directement avec le filtre choisi --}}
      <div class="recbox" id="recbox" hidden>
        <video id="recvid" autoplay muted playsinline></video>
        <div class="rectop"><span class="recdot" id="rectime">0:00</span><button type="button" class="recx" id="recclose" aria-label="{{ __('Fermer la caméra') }}">✕</button></div>
        <div class="recfilters" id="recfilters">
          @foreach (\App\Terrain\Growth\VideoEditor::FILTERS as $k => [$label, $css])
            <button type="button" data-css="{{ $css }}" data-filter="{{ $k }}" aria-pressed="{{ $k === 'normal' ? 'true' : 'false' }}">{{ __($label) }}</button>
          @endforeach
        </div>
        <div class="recbar">
          <button type="button" class="recflip" id="recflip" aria-label="{{ __('Changer de caméra') }}">⟲</button>
          <button type="button" class="recgo" id="recgo" aria-label="{{ __('Filmer') }}"><i></i></button>
          <span class="recflip" aria-hidden="true"></span>
        </div>
      </div>

      @if ($demo)<p class="meta" style="margin:8px 0 0">{{ __('Mode démo : sans vidéo, une vidéo d’exemple est utilisée.') }}</p>@endif

      {{-- 2. Titre --}}
      <label for="title" class="pub-title">{{ __('Titre') }}
        <input id="title" name="title" value="{{ old('title') }}" maxlength="150" placeholder="{{ __('Ex. : Mes buts de la saison') }}" required></label>

      @if (! empty($defis))
        <fieldset class="defipick"><legend><x-ico name="trophy" /> {{ __('Participer à un défi avec cette vidéo') }}</legend>
          <label class="check"><input type="radio" name="challenge_id" value="" checked> {{ __('Non, juste publier') }}</label>
          @foreach ($defis as $d)
            <label class="check"><input type="radio" name="challenge_id" value="{{ $d->id }}"> {{ __($d->title) }}@if ($d->prize) <small>· 🏆 {{ $d->prize }}</small>@endif</label>
          @endforeach
        </fieldset>
      @endif

      {{-- Tout le reste est facultatif --}}
      <details class="more-opts" @if ($errors->hasAny(['caption', 'competition', 'sub_fr', 'sub_en'])) open @endif>
        <summary><span>{{ __('Plus d’options') }}</span><small>{{ __('Compétition, langue, sous-titres') }}</small></summary>
        <div class="more-body">
          <label for="competition">{{ __('Compétition') }}<input id="competition" name="competition" maxlength="120" value="{{ old('competition') }}" placeholder="{{ __('Ex. : Ligue 1 Guinée') }}"></label>
          @include('web.partials.video-lang-fields', ['v' => null, 'auto' => $auto ?? false])
        </div>
      </details>

      <p class="field-error" id="pub-error" role="alert" @unless ($errors->any()) hidden @endunless>{{ $errors->first() }}</p>

      <div class="upbar" id="upbar" hidden>
        <div class="track"><i id="upfill"></i></div>
        <span id="uptext"></span>
      </div>
      <button class="btn big" type="submit" id="pub-btn">{{ __('Publier') }}</button>
    </form>
  </div>
</div>
@endsection

@push('scripts')
@php
  $pl = [
    'chooseFirst' => __('Choisis d’abord ta vidéo.'), 'notVideo' => __('Ce fichier n’est pas une vidéo. Choisis un fichier MP4 ou MOV.'),
    'tooBig' => __('La vidéo dépasse :n Mo. Coupe-la ou réduis sa qualité.', ['n' => $maxMb]), 'needTitle' => __('Donne un titre à ta vidéo.'),
    'sending' => __('Envoi de la vidéo… :p %'), 'publishing' => __('Publication…'), 'failed' => __('L’envoi a échoué. Vérifie ta connexion et réessaie : la vidéo reprendra où elle s’est arrêtée.'),
    'publish' => __('Publier'), 'retry' => __('Reprendre l’envoi'), 'leave' => __('La vidéo est en cours d’envoi. Quitter la page ?'),
    'length' => __('Durée de la vidéo publiée : :t'), 'noCam' => __('Autorise la caméra et le micro pour filmer (réglages du navigateur), ou choisis une vidéo dans ta galerie.'),
  ];
@endphp
<script>
(function () {
  var L = @json($pl), MAX = {{ $maxMb }} * 1024 * 1024, CHUNK = {{ $chunk }}, demo = {{ $demo ? 'true' : 'false' }};
  var form = document.getElementById('pub'), input = document.getElementById('video'), drop = document.getElementById('drop');
  var picked = document.getElementById('picked'), preview = document.getElementById('preview'), title = document.getElementById('title');
  var err = document.getElementById('pub-error'), btn = document.getElementById('pub-btn');
  var bar = document.getElementById('upbar'), fill = document.getElementById('upfill'), txt = document.getElementById('uptext');
  var file = null, uploadId = null, done = 0, busy = false;

  function fail(m) { err.textContent = m; err.hidden = false; }
  function mb(n) { return (n / 1048576).toFixed(n < 10485760 ? 1 : 0).replace('.', ',') + ' Mo'; }
  function rid() { var s = '', c = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; for (var i = 0; i < 32; i++) s += c[Math.floor(Math.random() * c.length)]; return s; }
  function nice(name) { var t = name.replace(/\.[^.]+$/, '').replace(/[_\-.]+/g, ' ').replace(/\s+/g, ' ').trim(); return /^(vid|img|mov|dsc|pxl|\d)/i.test(t) ? '' : t.charAt(0).toUpperCase() + t.slice(1); }

  function useFile(f) {
    err.hidden = true;
    if (!/^video\//.test(f.type) && !/\.(mp4|mov|webm|m4v|3gp)$/i.test(f.name)) { fail(L.notVideo); input.value = ''; return; }
    if (f.size > MAX) { fail(L.tooBig); input.value = ''; return; }
    file = f; uploadId = null; done = 0;
    preview.src = URL.createObjectURL(f);
    document.getElementById('fname').textContent = f.name;
    document.getElementById('fsize').textContent = mb(f.size);
    drop.hidden = true; picked.hidden = false; editor.hidden = false; filmBtn.hidden = true;
    preview.play().catch(function () {});
    if (!title.value) title.value = nice(f.name);
  }
  input.addEventListener('change', function () { var f = input.files[0]; if (f) useFile(f); });

  // ---------------------------------------------------------------- éditeur : filtres, coupe, vitesse, texte, son
  var editor = document.getElementById('editor'), filmBtn = document.getElementById('film');
  var ed = { filter: 'normal', start: 0, end: 0, speed: 1, mute: false, text_pos: 'milieu', text_color: 'blanc' };
  var sIn = document.getElementById('ed-start'), eIn = document.getElementById('ed-end'), cap = document.getElementById('edcap'), capIn = document.getElementById('caption');
  function t(s) { s = Math.max(0, Math.round(s)); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); }
  function save() { document.getElementById('edit').value = JSON.stringify(ed); }
  function paint() {
    var css = (editor.querySelector('.edf[aria-pressed="true"]') || {}).dataset; preview.style.filter = css && css.css !== 'none' ? css.css : '';
    preview.playbackRate = ed.speed; preview.muted = true;
    cap.textContent = capIn.value; cap.hidden = !capIn.value.trim();
    cap.className = 'cap edcap cap-' + ed.text_pos + ' cap-' + ed.text_color;
    document.getElementById('ed-start-l').textContent = t(ed.start); document.getElementById('ed-end-l').textContent = t(ed.end || preview.duration || 0);
    var len = (ed.end || preview.duration || 0) - ed.start; document.getElementById('ed-len').textContent = len > 0 ? L.length.replace(':t', t(len / ed.speed)) : '';
    save();
  }
  preview.addEventListener('loadedmetadata', function () {
    var d = preview.duration || 0, s = Math.round(d);
    if (s) document.getElementById('duration').value = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    sIn.max = eIn.max = d.toFixed(1); sIn.value = 0; eIn.value = d.toFixed(1); ed.start = 0; ed.end = 0; paint();
  });
  preview.addEventListener('timeupdate', function () {
    var end = ed.end || preview.duration; if (preview.currentTime >= end - 0.05 || preview.currentTime < ed.start - 0.3) preview.currentTime = ed.start;
  });
  sIn.addEventListener('input', function () { ed.start = Math.min(parseFloat(sIn.value), (ed.end || preview.duration) - 1); sIn.value = ed.start; preview.currentTime = ed.start; paint(); });
  eIn.addEventListener('input', function () {
    var v = Math.max(parseFloat(eIn.value), ed.start + 1); eIn.value = v; ed.end = v >= (preview.duration || 0) - 0.15 ? 0 : v; preview.currentTime = Math.max(ed.start, v - 1.5); paint();
  });
  editor.querySelectorAll('.edtabs [data-tab]').forEach(function (b) {
    b.addEventListener('click', function () {
      editor.querySelectorAll('.edtabs [data-tab]').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
      editor.querySelectorAll('.edpanel').forEach(function (p) { p.hidden = p.dataset.panel !== b.dataset.tab; });
    });
  });
  editor.querySelectorAll('.edf').forEach(function (b) {
    b.addEventListener('click', function () { editor.querySelectorAll('.edf').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); ed.filter = b.dataset.filter; paint(); });
  });
  editor.addEventListener('change', function (e) {
    if (e.target.name === 'ed_speed') ed.speed = parseFloat(e.target.value);
    if (e.target.name === 'ed_pos') ed.text_pos = e.target.value;
    if (e.target.name === 'ed_color') ed.text_color = e.target.value;
    if (e.target.id === 'ed-mute') ed.mute = e.target.checked;
    paint();
  });
  capIn.addEventListener('input', paint);
  document.getElementById('edplay').addEventListener('click', function () { if (preview.paused) preview.play().catch(function () {}); else preview.pause(); });
  preview.addEventListener('play', function () { document.getElementById('edplay').textContent = '❚❚'; preview.playbackRate = ed.speed; });
  preview.addEventListener('pause', function () { document.getElementById('edplay').textContent = '▶'; });

  // ---------------------------------------------------------------- caméra : filmer avec le filtre en direct
  var recbox = document.getElementById('recbox'), recvid = document.getElementById('recvid'), recgo = document.getElementById('recgo');
  var recStream = null, recorder = null, chunks = [], facing = 'environment', recTimer = null, recFilter = 'normal', recCss = 'none';
  async function openCam() {
    if (recStream) recStream.getTracks().forEach(function (x) { x.stop(); });
    try {
      recStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 720 }, height: { ideal: 1280 } }, audio: true });
      recvid.srcObject = recStream; recbox.hidden = false; document.body.classList.add('recording-open');
    } catch (e) { fail(L.noCam); }
  }
  filmBtn.addEventListener('click', function () { if (!window.MediaRecorder) { input.setAttribute('capture', 'environment'); input.click(); return; } openCam(); });
  document.getElementById('recflip').addEventListener('click', function () { if (recorder && recorder.state === 'recording') return; facing = facing === 'user' ? 'environment' : 'user'; openCam(); });
  function closeCam() { if (recStream) recStream.getTracks().forEach(function (x) { x.stop(); }); recStream = null; recbox.hidden = true; document.body.classList.remove('recording-open'); clearInterval(recTimer); }
  document.getElementById('recclose').addEventListener('click', function () { if (recorder && recorder.state === 'recording') recorder.stop(); closeCam(); });
  document.getElementById('recfilters').addEventListener('click', function (e) {
    var b = e.target.closest('[data-filter]'); if (!b) return;
    this.querySelectorAll('[data-filter]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
    recFilter = b.dataset.filter; recCss = b.dataset.css; recvid.style.filter = recCss === 'none' ? '' : recCss;
  });
  recgo.addEventListener('click', function () {
    if (recorder && recorder.state === 'recording') { recorder.stop(); return; }
    var types = ['video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'], type = '';
    for (var i = 0; i < types.length; i++) { if (MediaRecorder.isTypeSupported(types[i])) { type = types[i]; break; } }
    chunks = []; recorder = new MediaRecorder(recStream, type ? { mimeType: type, videoBitsPerSecond: 2500000 } : undefined);
    recorder.ondataavailable = function (e) { if (e.data.size) chunks.push(e.data); };
    recorder.onstop = function () {
      var mime = (recorder.mimeType || type || 'video/webm').split(';')[0], ext = mime.indexOf('mp4') > -1 ? 'mp4' : 'webm';
      var f = new File(chunks, 'frikip-' + Date.now() + '.' + ext, { type: mime });
      closeCam(); recgo.classList.remove('on');
      // le filtre choisi en filmant est repris dans l'éditeur
      var fb = editor.querySelector('.edf[data-filter="' + recFilter + '"]'); if (fb) fb.click();
      useFile(f);
    };
    recorder.start(1000); recgo.classList.add('on');
    var t0 = Date.now(), rt = document.getElementById('rectime');
    recTimer = setInterval(function () { var s = (Date.now() - t0) / 1000; rt.textContent = t(s); if (s >= 180) recorder.stop(); }, 250);
  });

  function progress(p, label) { bar.hidden = false; fill.style.width = p + '%'; txt.textContent = label; }

  // Envoie les morceaux un par un ; en cas de coupure, réessaie 4 fois puis permet de reprendre
  function sendChunks() {
    var total = Math.ceil(file.size / CHUNK);
    uploadId = uploadId || rid();
    function one(i, attempt) {
      if (i >= total) return Promise.resolve();
      progress(Math.round(i / total * 100), L.sending.replace(':p', Math.round(i / total * 100)));
      return fetch(@json(route('publish.chunk')), {
        method: 'POST', body: file.slice(i * CHUNK, Math.min(file.size, (i + 1) * CHUNK)),
        headers: { 'Content-Type': 'application/octet-stream', 'Accept': 'application/json', 'X-CSRF-TOKEN': Terrain.csrf,
          'X-Upload-Id': uploadId, 'X-Chunk-Index': i, 'X-Chunk-Total': total, 'X-File-Size': file.size }
      }).then(function (r) { return r.json().then(function (d) { return { r: r, d: d }; }, function () { return { r: r, d: {} }; }); }).then(function (x) {
        if (x.r.ok) { done = x.d.next || i + 1; return one(done, 0); }
        if (x.r.status === 409 && x.d.next != null) return one(x.d.next, attempt);
        if (x.r.status === 401) { location.href = Terrain.loginUrl; throw new Error('login'); }
        if (x.r.status < 500 && x.r.status !== 429) throw new Error(x.d.error || L.failed);
        throw new Error('retry');
      }).catch(function (e) {
        if (e.message !== 'retry' && e.message !== 'Failed to fetch' && e.name !== 'TypeError') throw e;
        if (attempt >= 4) throw new Error(L.failed);
        return new Promise(function (ok) { setTimeout(ok, 1500 * (attempt + 1)); }).then(function () { return one(i, attempt + 1); });
      });
    }
    return one(done, 0);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault(); if (busy) return;
    err.hidden = true;
    if (!file && !demo) { fail(L.chooseFirst); return; }
    if (!title.value.trim()) { fail(L.needTitle); title.focus(); return; }
    busy = true; btn.disabled = true;
    (file ? sendChunks() : Promise.resolve()).then(function () {
      progress(100, L.publishing); btn.textContent = L.publishing;
      var fd = new FormData(form); fd.delete('video');
      if (file) { fd.append('upload_id', uploadId); fd.append('file_name', file.name); }
      return fetch(form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': Terrain.csrf } })
        .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || L.failed); return d; }); });
    }).then(function (d) { busy = false; location.href = d.url; }).catch(function (e) {
      busy = false; btn.disabled = false; btn.textContent = done ? L.retry : L.publish; fail(e.message || L.failed);
      if (/terminé|incomplète|introuvable/.test(e.message || '')) { uploadId = null; done = 0; }
    });
  });
  window.addEventListener('beforeunload', function (e) { if (busy) { e.preventDefault(); e.returnValue = L.leave; } });
})();
</script>
@endpush
