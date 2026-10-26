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

      {{-- 1. Choisir la vidéo --}}
      <label for="video" class="drop" id="drop">
        <span class="drop-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M6.5 9.5 12 4l5.5 5.5"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg></span>
        <b>{{ __('Choisir ma vidéo') }}</b>
        <span>{{ __('Depuis ta galerie ou ta caméra. MP4 ou MOV, jusqu’à :n Mo.', ['n' => $maxMb]) }}</span>
      </label>
      <div class="picked" id="picked" hidden>
        <video id="preview" muted playsinline controls preload="metadata"></video>
        <div class="picked-info">
          <b id="fname"></b>
          <span class="meta" id="fsize"></span>
          <label for="video" class="btn ghost sm">{{ __('Changer de vidéo') }}</label>
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
        <summary><span>{{ __('Plus d’options') }}</span><small>{{ __('Texte sur la vidéo, compétition, langue, sous-titres') }}</small></summary>
        <div class="more-body">
          <label for="caption">{{ __('Texte affiché sur la vidéo') }}<input id="caption" name="caption" maxlength="120" value="{{ old('caption') }}" placeholder="{{ __('Ex. : 14 buts cette saison, regardez le n°7') }}"></label>
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

  input.addEventListener('change', function () {
    var f = input.files[0]; if (!f) return;
    err.hidden = true;
    if (!/^video\//.test(f.type) && !/\.(mp4|mov|webm|m4v|3gp)$/i.test(f.name)) { fail(L.notVideo); input.value = ''; return; }
    if (f.size > MAX) { fail(L.tooBig); input.value = ''; return; }
    file = f; uploadId = null; done = 0;
    preview.src = URL.createObjectURL(f);
    document.getElementById('fname').textContent = f.name;
    document.getElementById('fsize').textContent = mb(f.size);
    drop.hidden = true; picked.hidden = false;
    if (!title.value) title.value = nice(f.name);
    title.focus();
  });
  preview.addEventListener('loadedmetadata', function () {
    var s = Math.round(preview.duration || 0); if (!s) return;
    document.getElementById('duration').value = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
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
