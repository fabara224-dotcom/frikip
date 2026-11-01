{{-- Stories : ronds en haut de l'Actu, création (photo réduite avant l'envoi, vidéo envoyée par morceaux) et lecture plein écran. --}}
@php
  $groups = \App\Http\Controllers\StoryController::groups($me);
  $myInitials = $me ? collect(preg_split('/\s+/', trim($me['name'] ?? '?')))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') : '';
  $hasMine = $groups && $groups[0]['mine'];
@endphp
<div class="stories" aria-label="{{ __('Stories') }}">
  @if ($me)
    <button type="button" class="story-new" id="story-new">
      <span class="sring add"><span class="av">{{ $myInitials }}</span><i>+</i></span><small>{{ __('Créer') }}</small>
    </button>
  @else
    <a class="story-new" href="{{ route('phone.login') }}"><span class="sring add"><span class="av">+</span></span><small>{{ __('Créer') }}</small></a>
  @endif
  @foreach ($groups as $i => $g)
    <button type="button" class="story-open" data-group="{{ $i }}">
      <span class="sring @if (! $g['seen'] && ! $g['mine']) unseen @endif"><span class="av">{{ $g['initials'] }}</span></span>
      <small>{{ $g['mine'] ? __('Ma story') : \Illuminate\Support\Str::limit($g['name'], 12) }}</small>
    </button>
  @endforeach
  @if (! $groups && ! $errors->has('media'))
    <span class="stories-hint">{{ __('Partage une photo ou une vidéo de ton entraînement : elle reste visible 24 h.') }}</span>
  @endif
</div>
@error('media')<p class="field-error">{{ __('Story non publiée :') }} {{ $message }}</p>@enderror

@if ($me)
<div class="sheet-ov" id="story-sheet" hidden>
  <div class="sheet" role="dialog" aria-labelledby="story-sheet-title">
    <div class="hd"><b id="story-sheet-title">{{ __('Nouvelle story') }}</b><button class="x" type="button" data-close aria-label="{{ __('Fermer') }}">×</button></div>
    <form class="bd" id="story-form" method="POST" action="{{ route('fkstories.store') }}" enctype="multipart/form-data">@csrf
      <label class="story-pick" id="story-pick">
        <input type="file" id="story-file" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm" class="visually-hidden">
        <span class="story-pick-empty"><x-ico name="image" /><b>{{ __('Choisir une photo ou une vidéo') }}</b><small>{{ __('Vidéo : 60 secondes maximum') }}</small></span>
        <img id="story-prev-img" alt="" hidden><video id="story-prev-vid" muted playsinline loop hidden></video>
      </label>
      <input name="caption" maxlength="200" placeholder="{{ __('Ajoute un texte (facultatif)') }}" aria-label="{{ __('Texte de la story') }}">
      <div class="upbar" id="story-up" hidden><div class="track"><i></i></div><span></span></div>
      <p class="field-error" id="story-err" hidden></p>
      <button class="btn" type="submit" id="story-send" disabled>{{ __('Partager pour 24 h') }}</button>
    </form>
  </div>
</div>
@endif

<div class="sview" id="sview" hidden role="dialog" aria-label="{{ __('Story') }}">
  <div class="sview-box">
    <div class="sview-bars" id="sview-bars"></div>
    <header class="sview-hd">
      <a class="sview-who" id="sview-who"><span class="av" id="sview-av"></span><span><b id="sview-name"></b><small id="sview-when"></small></span></a>
      <button type="button" class="sview-x" id="sview-close" aria-label="{{ __('Fermer') }}">×</button>
    </header>
    <div class="sview-media" id="sview-media"></div>
    <p class="sview-cap" id="sview-cap" hidden></p>
    <button type="button" class="sview-nav prev" id="sview-prev" aria-label="{{ __('Précédente') }}"></button>
    <button type="button" class="sview-nav next" id="sview-next" aria-label="{{ __('Suivante') }}"></button>
    <footer class="sview-ft">
      <span id="sview-views"></span>
      @if ($me)
        <form method="POST" id="sview-del" data-action="{{ route('fkstories.destroy', '__ID__') }}" onsubmit="return confirm(@js(__('Supprimer cette story ?')))">@csrf<button type="submit">{{ __('Supprimer') }}</button></form>
        <form method="POST" id="sview-rep" data-action="{{ route('fkstories.report', '__ID__') }}" onsubmit="return confirm(@js(__('Signaler cette story ?')))">@csrf<button type="submit">{{ __('Signaler') }}</button></form>
      @endif
    </footer>
  </div>
</div>

<script>
// Lancé une fois la page chargée : window.Terrain (jeton CSRF, connexion) est défini plus bas dans la mise en page
document.addEventListener('DOMContentLoaded', function () {
  var G = @json($groups), T = window.Terrain;
  var viewUrl = @json(route('fkstories.view', '__ID__'));
  var box = document.getElementById('sview'), media = document.getElementById('sview-media'), bars = document.getElementById('sview-bars');
  var gi = 0, ii = 0, timer = null, started = 0, dur = 5000, paused = false, left = 0;

  function el(id) { return document.getElementById(id); }
  function stop() { clearTimeout(timer); timer = null; var v = media.querySelector('video'); if (v) v.pause(); }
  function open(g) { gi = g; var it = G[gi].items; ii = 0; for (var k = 0; k < it.length; k++) { if (!it[k].seen) { ii = k; break; } } box.hidden = false; document.body.style.overflow = 'hidden'; show(); }
  function close() { stop(); box.hidden = true; document.body.style.overflow = ''; media.innerHTML = ''; }
  function next() { stop(); if (ii < G[gi].items.length - 1) { ii++; show(); } else if (gi < G.length - 1) { gi++; ii = 0; show(); } else { close(); } }
  function prev() { stop(); if (ii > 0) { ii--; show(); } else if (gi > 0) { gi--; ii = G[gi].items.length - 1; show(); } else { show(); } }
  function run(ms) {
    dur = ms; started = Date.now(); left = ms;
    var bar = bars.children[ii].querySelector('i');
    bar.style.transition = 'none'; bar.style.width = '0%'; void bar.offsetWidth;
    bar.style.transition = 'width ' + ms + 'ms linear'; bar.style.width = '100%';
    timer = setTimeout(next, ms);
  }
  function show() {
    var g = G[gi], s = g.items[ii];
    bars.innerHTML = g.items.map(function (x, k) { return '<span><i style="width:' + (k < ii ? '100%' : '0%') + '"></i></span>'; }).join('');
    el('sview-av').textContent = g.initials; el('sview-name').textContent = g.name; el('sview-when').textContent = s.when;
    var who = el('sview-who'); if (g.profile) who.href = g.profile; else who.removeAttribute('href');
    var cap = el('sview-cap'); cap.hidden = !s.caption; cap.textContent = s.caption || '';
    el('sview-views').textContent = g.mine ? (s.views + ' ' + (s.views > 1 ? @json(__('vues')) : @json(__('vue')))) : '';
    var del = el('sview-del'), rep = el('sview-rep');
    if (del) { del.hidden = !g.mine; del.action = del.dataset.action.replace('__ID__', s.id); }
    if (rep) { rep.hidden = g.mine; rep.action = rep.dataset.action.replace('__ID__', s.id); }
    media.innerHTML = '';
    if (s.type === 'video') {
      var v = document.createElement('video'); v.src = s.src; v.playsInline = true; v.autoplay = true; v.setAttribute('playsinline', '');
      v.onloadedmetadata = function () { run(Math.min(60, v.duration || 15) * 1000); };
      v.onerror = function () { run(3000); };
      media.appendChild(v);
      v.play().catch(function () { v.muted = true; v.play().catch(function () {}); });
    } else {
      var img = new Image(); img.alt = ''; img.src = s.src; media.appendChild(img);
      img.onload = img.onerror = function () { run(5000); };
    }
    if (T.loggedIn && !g.mine && !s.seen) { s.seen = true; T.post(viewUrl.replace('__ID__', s.id)).catch(function () {}); }
    var ring = document.querySelector('.story-open[data-group="' + gi + '"] .sring');
    if (ring && g.items.every(function (x) { return x.seen; })) ring.classList.remove('unseen');
  }
  // Appui long : pause
  function hold(on) {
    if (box.hidden) return; var v = media.querySelector('video'), bar = bars.children[ii] && bars.children[ii].querySelector('i');
    if (on && timer) { paused = true; clearTimeout(timer); timer = null; left = Math.max(300, dur - (Date.now() - started)); if (v) v.pause();
      if (bar) { var w = getComputedStyle(bar).width; bar.style.transition = 'none'; bar.style.width = w; } }
    else if (!on && paused) { paused = false; if (v) v.play().catch(function () {}); started = Date.now() - (dur - left);
      if (bar) { bar.style.transition = 'width ' + left + 'ms linear'; bar.style.width = '100%'; } timer = setTimeout(next, left); }
  }
  document.querySelectorAll('.story-open').forEach(function (b) { b.addEventListener('click', function () { open(+b.dataset.group); }); });
  el('sview-close').onclick = close; el('sview-next').onclick = next; el('sview-prev').onclick = prev;
  ['sview-next', 'sview-prev'].forEach(function (id) {
    var b = el(id), t = null;
    b.addEventListener('pointerdown', function () { t = setTimeout(function () { hold(true); t = 'held'; }, 250); });
    b.addEventListener('pointerup', function (e) { if (t === 'held') { e.preventDefault(); hold(false); b.dataset.skip = '1'; } else clearTimeout(t); t = null; });
    b.addEventListener('click', function (e) { if (b.dataset.skip) { e.stopImmediatePropagation(); delete b.dataset.skip; } }, true);
  });
  document.addEventListener('keydown', function (e) { if (box.hidden) return; if (e.key === 'Escape') close(); if (e.key === 'ArrowRight') next(); if (e.key === 'ArrowLeft') prev(); });

  // ---------------- création
  var sheet = el('story-sheet'); if (!sheet) return;
  var form = el('story-form'), file = el('story-file'), send = el('story-send'), err = el('story-err'), up = el('story-up');
  var chosen = null, kind = null, MAXV = {{ \App\Http\Controllers\StoryController::VIDEO_MAX_MB }} * 1024 * 1024, CHUNK = {{ \App\Http\Controllers\PublishController::CHUNK }};
  el('story-new').onclick = function () { sheet.hidden = false; };
  sheet.addEventListener('click', function (e) { if (e.target === sheet || e.target.closest('[data-close]')) sheet.hidden = true; });
  function fail(m) { err.hidden = false; err.textContent = m; send.disabled = !chosen; send.textContent = @json(__('Partager pour 24 h')); up.hidden = true; }
  file.addEventListener('change', function () {
    err.hidden = true; chosen = file.files && file.files[0]; kind = chosen && /^video\//.test(chosen.type) ? 'video' : 'image';
    var img = el('story-prev-img'), vid = el('story-prev-vid'); img.hidden = vid.hidden = true;
    if (!chosen) { send.disabled = true; return; }
    el('story-pick').classList.add('has');
    var url = URL.createObjectURL(chosen);
    if (kind === 'video') {
      if (chosen.size > MAXV) { chosen = null; return fail(@json(__('Vidéo trop lourde (60 Mo maximum).'))); }
      vid.src = url; vid.hidden = false; vid.play().catch(function () {});
      vid.onloadedmetadata = function () { if (vid.duration > 61) { chosen = null; fail(@json(__('Vidéo trop longue : 60 secondes maximum.'))); } };
    } else { img.src = url; img.hidden = false; }
    send.disabled = false;
  });
  // Photo réduite à 1440 px (JPEG) : envoi rapide en 3G et pas de blocage par la limite d'envoi du serveur
  function shrink(f) {
    return new Promise(function (ok) {
      if (f.size < 900 * 1024 || !window.createImageBitmap) return ok(f);
      createImageBitmap(f).then(function (bmp) {
        var k = Math.min(1, 1440 / Math.max(bmp.width, bmp.height)), c = document.createElement('canvas');
        c.width = Math.round(bmp.width * k); c.height = Math.round(bmp.height * k);
        c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
        c.toBlob(function (b) { ok(b ? new File([b], 'story.jpg', { type: 'image/jpeg' }) : f); }, 'image/jpeg', 0.85);
      }).catch(function () { ok(f); });
    });
  }
  function chunks(f) {
    var id = Array.from(crypto.getRandomValues(new Uint8Array(16)), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('').slice(0, 30);
    var total = Math.ceil(f.size / CHUNK), bar = up.querySelector('i'), lab = up.querySelector('span');
    up.hidden = false;
    function part(i) {
      if (i >= total) return Promise.resolve(id);
      return fetch(@json(route('publish.chunk')), { method: 'POST', body: f.slice(i * CHUNK, Math.min(f.size, (i + 1) * CHUNK)),
        headers: { 'X-CSRF-TOKEN': T.csrf, 'X-Upload-Id': id, 'X-Chunk-Index': i, 'X-Chunk-Total': total, 'X-File-Size': f.size, 'Accept': 'application/json' } })
        .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || 'Erreur'); return d; }); })
        .then(function () { var p = Math.round((i + 1) / total * 100); bar.style.width = p + '%'; lab.textContent = p + ' %'; return part(i + 1); });
    }
    return part(0);
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault(); if (!chosen) return;
    send.disabled = true; send.textContent = @json(__('Envoi…')); err.hidden = true;
    var fd = new FormData(form);
    var go = kind === 'video'
      ? chunks(chosen).then(function (id) { fd.append('upload_id', id); fd.append('file_name', chosen.name || 'story.mp4'); })
      : shrink(chosen).then(function (f) { fd.append('media', f, f.name || 'story.jpg'); });
    go.then(function () { return fetch(form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': T.csrf } }); })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) {
        if (r.ok && d.redirect) { location.href = d.redirect; return; }
        var first = d.errors ? Object.values(d.errors)[0][0] : d.message;
        throw new Error(first || @json(__('Envoi impossible. Réessaie.')));
      }); })
      .catch(function (x) { fail(x && x.message ? x.message : @json(__('Envoi impossible. Vérifie ta connexion et réessaie.'))); });
  });
});
</script>
