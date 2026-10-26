@extends('layouts.web')

@section('title', __('Commander un montage').' – '.config('terrain.app_name'))

@php
  [$label, $price, $days, $note] = $pack;
@endphp

@section('content')
<div class="page gut">
  <div class="narrow pub">
    <h1>{{ __($label) }}</h1>
    <p class="pub-lead">{{ __($note) }} <b>{{ \App\Terrain\Region::format($price, \App\Terrain\Growth\Payments::currency()) }}</b></p>

    <form id="order" novalidate>
      <input type="hidden" name="package" value="{{ $i }}">
      <input class="visually-hidden" id="rushes" type="file" accept="video/*,.mp4,.mov,.webm,.m4v" multiple>
      <label for="rushes" class="drop small" id="drop">
        <span class="drop-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M6.5 9.5 12 4l5.5 5.5"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg></span>
        <b>{{ __('Ajouter mes vidéos') }}</b>
        <span>{{ __('Jusqu’à 5 vidéos de :n Mo maximum chacune.', ['n' => $maxMb]) }}</span>
      </label>
      <ul class="rushlist" id="rushlist"></ul>
      <label for="rush_link">{{ __('Ou un lien vers tes vidéos (Google Drive, WeTransfer…)') }}<input id="rush_link" name="rush_link" type="url" placeholder="https://"></label>
      <label for="notes">{{ __('Ce qu’il faut montrer') }}<textarea id="notes" name="notes" rows="3" maxlength="1500" placeholder="{{ __('Ex. : je porte le n°10 en maillot jaune, garde surtout mes dribbles et mon but de la 2e vidéo.') }}"></textarea></label>
      <label for="whatsapp">{{ __('Ton numéro WhatsApp') }}<input id="whatsapp" name="whatsapp" type="tel" value="{{ $member->phone ?? '' }}" placeholder="6XX XX XX XX" required></label>

      <fieldset class="langfs"><legend><b>{{ __('Paiement') }}</b></legend>
        <div class="plans">
          @foreach (['om' => ['Orange Money', '#FF7900'], 'momo' => ['MTN MoMo', '#FFCB05']] as $k => [$name, $color])
            <label class="opt" style="flex-direction:row;align-items:center;gap:10px"><input type="radio" name="method" value="{{ $k }}" @checked($k === 'om')><span style="width:20px;height:20px;border-radius:50%;background:{{ $color }}"></span>{{ $name }}</label>
          @endforeach
        </div>
        <label for="phone">{{ __('Numéro Mobile Money') }}<input id="phone" name="phone" type="tel" value="{{ $member->phone ?? '' }}" placeholder="6XX XX XX XX" required></label>
      </fieldset>

      <p class="field-error" id="err" role="alert" hidden></p>
      <div class="upbar" id="upbar" hidden><div class="track"><i id="upfill"></i></div><span id="uptext"></span></div>
      <button class="btn big" type="submit" id="go">{{ __('Envoyer et payer') }}</button>
      <p class="meta" style="margin:0">{{ __('Tes vidéos ne sont vues que par notre équipe de montage.') }}</p>
    </form>
  </div>
</div>
@endsection

@push('scripts')
@php
  $ol = ['sending' => __('Envoi de la vidéo :n/:t… :p %'), 'paying' => __('Ouverture du paiement…'), 'need' => __('Envoie au moins une vidéo, ou colle un lien vers tes vidéos.'),
    'tooBig' => __('La vidéo dépasse :n Mo. Coupe-la ou réduis sa qualité.', ['n' => $maxMb]), 'notVideo' => __('Ce fichier n’est pas une vidéo. Choisis un fichier MP4 ou MOV.'),
    'failed' => __('L’envoi a échoué. Vérifie ta connexion et réessaie.'), 'remove' => __('Retirer'), 'max' => __('5 vidéos maximum.'), 'go' => __('Envoyer et payer')];
@endphp
<script>
(function () {
  var L = @json($ol), MAX = {{ $maxMb }} * 1048576, CHUNK = {{ $chunk }};
  var form = document.getElementById('order'), input = document.getElementById('rushes'), list = document.getElementById('rushlist');
  var err = document.getElementById('err'), go = document.getElementById('go'), bar = document.getElementById('upbar'), fill = document.getElementById('upfill'), txt = document.getElementById('uptext');
  var files = [], busy = false;
  function fail(m) { err.textContent = m; err.hidden = false; }
  function mb(n) { return (n / 1048576).toFixed(1).replace('.', ',') + ' Mo'; }
  function rid() { var s = '', c = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; for (var i = 0; i < 32; i++) s += c[Math.floor(Math.random() * c.length)]; return s; }
  function draw() {
    list.innerHTML = '';
    files.forEach(function (f, i) {
      var li = document.createElement('li'); li.innerHTML = '<span></span><small></small><button type="button" class="linkbtn"></button>';
      li.querySelector('span').textContent = f.file.name; li.querySelector('small').textContent = mb(f.file.size);
      var b = li.querySelector('button'); b.textContent = L.remove; b.onclick = function () { files.splice(i, 1); draw(); };
      list.appendChild(li);
    });
  }
  input.addEventListener('change', function () {
    err.hidden = true;
    Array.prototype.forEach.call(input.files, function (f) {
      if (files.length >= 5) { fail(L.max); return; }
      if (!/^video\//.test(f.type) && !/\.(mp4|mov|webm|m4v|3gp)$/i.test(f.name)) { fail(L.notVideo); return; }
      if (f.size > MAX) { fail(L.tooBig); return; }
      files.push({ file: f, id: null, done: 0 });
    });
    input.value = ''; draw();
  });
  function sendOne(f, n) {
    var total = Math.ceil(f.file.size / CHUNK); f.id = f.id || rid();
    function one(i, attempt) {
      if (i >= total) return Promise.resolve();
      var p = Math.round(i / total * 100);
      fill.style.width = p + '%'; txt.textContent = L.sending.replace(':n', n + 1).replace(':t', files.length).replace(':p', p);
      return fetch(@json(route('publish.chunk')), { method: 'POST', body: f.file.slice(i * CHUNK, Math.min(f.file.size, (i + 1) * CHUNK)),
        headers: { 'Content-Type': 'application/octet-stream', 'Accept': 'application/json', 'X-CSRF-TOKEN': Terrain.csrf, 'X-Upload-Id': f.id, 'X-Chunk-Index': i, 'X-Chunk-Total': total, 'X-File-Size': f.file.size } })
        .then(function (r) { return r.json().then(function (d) { return { r: r, d: d }; }, function () { return { r: r, d: {} }; }); })
        .then(function (x) {
          if (x.r.ok) { f.done = x.d.next || i + 1; return one(f.done, 0); }
          if (x.r.status === 409 && x.d.next != null) return one(x.d.next, attempt);
          if (x.r.status < 500 && x.r.status !== 429) throw new Error(x.d.error || L.failed);
          throw new Error('retry');
        }).catch(function (e) {
          if (e.message !== 'retry' && e.name !== 'TypeError') throw e;
          if (attempt >= 4) throw new Error(L.failed);
          return new Promise(function (ok) { setTimeout(ok, 1500 * (attempt + 1)); }).then(function () { return one(i, attempt + 1); });
        });
    }
    return one(f.done, 0);
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault(); if (busy) return; err.hidden = true;
    var link = document.getElementById('rush_link').value.trim();
    if (!files.length && !link) { fail(L.need); return; }
    busy = true; go.disabled = true; bar.hidden = false;
    files.reduce(function (p, f, n) { return p.then(function () { return sendOne(f, n); }); }, Promise.resolve()).then(function () {
      fill.style.width = '100%'; txt.textContent = L.paying;
      var body = { package: form.package.value, notes: form.notes.value, whatsapp: form.whatsapp.value, rush_link: link || null,
        method: (form.querySelector('[name=method]:checked') || {}).value, phone: form.phone.value,
        uploads: files.map(function (f) { return { id: f.id, name: f.file.name }; }) };
      return fetch(@json(route('video.order.store')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': Terrain.csrf }, body: JSON.stringify(body) })
        .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || L.failed); return d; }); });
    }).then(function (d) { busy = false; location.href = d.url; }).catch(function (e) {
      busy = false; go.disabled = false; fail(e.message || L.failed);
      if (/terminé|introuvable|incomplète/.test(e.message || '')) files.forEach(function (f) { f.id = null; f.done = 0; });
    });
  });
  window.addEventListener('beforeunload', function (e) { if (busy) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
@endpush
