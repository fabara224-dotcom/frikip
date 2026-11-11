/*
 * STORIES — façon Instagram : photo ou vidéo visible 24 h.
 * Lecteur plein écran : barres de progression, toucher à gauche / à droite pour reculer / avancer,
 * appui long pour mettre en pause, glisser vers le bas ou Échap pour fermer,
 * passage automatique à la personne suivante. Création : photo ou vidéo + légende.
 */
window.Frikip = window.Frikip || {};
(function () {
  var U = Frikip.ui, api = Frikip.api;
  var PHOTO_MS = 5000;
  var box = null, groups = [], gi = 0, ii = 0, timer = null, started = 0, elapsed = 0, paused = false, onClose = null, media = null;

  function who(g) { return { name: g.name, color: g.color || '#16A34A', photo: g.avatar || null }; }

  function close() {
    stopTimer();
    if (media && media.pause) media.pause();
    if (box) { box.remove(); box = null; }
    document.removeEventListener('keydown', onKey);
    document.documentElement.classList.remove('sv-open');
    if (onClose) onClose(groups);
  }

  function stopTimer() { if (timer) cancelAnimationFrame(timer); timer = null; }

  function bars() {
    var g = groups[gi];
    return g.items.map(function (it, k) {
      return '<span class="sv-bar"><i style="width:' + (k < ii ? 100 : 0) + '%"' + (k === ii ? ' data-cur' : '') + '></i></span>';
    }).join('');
  }

  function show() {
    stopTimer();
    if (media && media.pause) media.pause();
    var g = groups[gi], it = g.items[ii];
    paused = false; elapsed = 0;
    box.querySelector('[data-bars]').innerHTML = bars();
    box.querySelector('[data-head]').innerHTML =
      '<a class="sv-who" ' + (g.profile ? 'href="' + U.esc(g.profile) + '" data-sv-profile' : '') + '>' + U.avatar(who(g), 'sv') +
      '<b>' + U.esc(g.mine ? 'Ta story' : g.name) + '</b><small>' + U.esc(it.when || '') + '</small></a>';
    var stage = box.querySelector('[data-stage]');
    stage.innerHTML = it.type === 'video'
      ? '<video src="' + U.esc(it.src) + '" playsinline preload="auto"></video>'
      : '<img src="' + U.esc(it.src) + '" alt="">';
    box.querySelector('[data-cap]').textContent = it.caption || '';
    box.querySelector('[data-cap]').hidden = !it.caption;
    var foot = box.querySelector('[data-foot]');
    foot.innerHTML = g.mine
      ? '<span class="sv-views">' + U.icon('eye') + (it.views != null ? U.n(it.views) + ' vue' + (it.views > 1 ? 's' : '') : 'Visible 24 h') + '</span>'
      : (g.profile ? '<a class="sv-reply" href="' + U.esc(g.profile) + '" data-sv-profile>Voir le profil de ' + U.esc((g.name || '').split(' ')[0]) + '</a>' : '');
    media = stage.firstChild;
    if (!it.seen && !g.mine) { it.seen = true; api.storyView(it.id); }
    if (g.items.every(function (x) { return x.seen; })) g.seen = true;
    if (it.type === 'video') {
      media.muted = false;
      var p = media.play();
      if (p && p.catch) p.catch(function () { media.muted = true; media.play().catch(function () {}); box.classList.add('sv-muted'); });
      media.addEventListener('ended', next);
      media.addEventListener('timeupdate', function () {
        var cur = box && box.querySelector('[data-cur]');
        if (cur && media.duration) cur.style.width = (media.currentTime / media.duration * 100) + '%';
      });
    } else {
      started = performance.now();
      tick();
    }
  }

  function tick() {
    timer = requestAnimationFrame(function (t) {
      if (!box) return;
      if (!paused) {
        var p = (elapsed + (t - started)) / PHOTO_MS;
        var cur = box.querySelector('[data-cur]'); if (cur) cur.style.width = Math.min(100, p * 100) + '%';
        if (p >= 1) { next(); return; }
      }
      tick();
    });
  }

  function pause(on) {
    if (on === paused) return;
    paused = on;
    box.classList.toggle('sv-paused', on);
    var it = groups[gi].items[ii];
    if (it.type === 'video') { if (on) media.pause(); else media.play().catch(function () {}); }
    else if (on) elapsed += performance.now() - started; else started = performance.now();
  }

  function next() {
    var g = groups[gi];
    if (ii < g.items.length - 1) { ii++; show(); return; }
    if (gi < groups.length - 1) { gi++; ii = firstUnseen(groups[gi]); show(); return; }
    close();
  }
  function prev() {
    if (ii > 0) { ii--; show(); return; }
    if (gi > 0) { gi--; ii = 0; show(); return; }
    show();
  }
  function firstUnseen(g) { for (var k = 0; k < g.items.length; k++) if (!g.items[k].seen) return k; return 0; }

  function onKey(e) {
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowRight') next();
    else if (e.key === 'ArrowLeft') prev();
    else if (e.key === ' ') { e.preventDefault(); pause(!paused); }
  }

  function open(list, g, onDone) {
    if (!list || !list.length) return;
    groups = list; gi = g || 0; ii = firstUnseen(groups[gi]); onClose = onDone || null;
    box = document.createElement('div');
    box.className = 'sv';
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', 'Story');
    box.innerHTML = '<div class="sv-frame"><div class="sv-stage" data-stage></div>' +
      '<div class="sv-top"><div class="sv-bars" data-bars></div><div class="sv-head"><div data-head></div>' +
      '<button class="sv-btn" type="button" data-sv-sound aria-label="Activer le son">' + U.icon('volume') + '</button>' +
      '<button class="sv-btn" type="button" data-sv-close aria-label="Fermer">' + U.icon('close') + '</button></div></div>' +
      '<button class="sv-zone left" type="button" data-sv-prev aria-label="Story précédente"></button>' +
      '<button class="sv-zone right" type="button" data-sv-next aria-label="Story suivante"></button>' +
      '<p class="sv-cap" data-cap hidden></p><div class="sv-foot" data-foot></div></div>';
    document.body.appendChild(box);
    document.documentElement.classList.add('sv-open');
    document.addEventListener('keydown', onKey);

    var downAt = 0, holdT = null, y0 = null;
    box.addEventListener('pointerdown', function (e) {
      if (e.target.closest('.sv-btn, a')) return;
      downAt = Date.now(); y0 = e.clientY;
      holdT = setTimeout(function () { pause(true); }, 220);
    });
    box.addEventListener('pointerup', function (e) {
      clearTimeout(holdT);
      if (y0 != null && e.clientY - y0 > 90) { y0 = null; close(); return; }
      y0 = null;
      if (paused) { pause(false); e.preventDefault(); return; }
    });
    box.addEventListener('click', function (e) {
      if (e.target.closest('[data-sv-close]')) { close(); return; }
      if (e.target.closest('[data-sv-sound]')) { if (media) { media.muted = false; box.classList.remove('sv-muted'); } return; }
      if (e.target.closest('[data-sv-profile]')) { close(); return; }
      if (Date.now() - downAt > 260) return;   // c'était un appui long
      if (e.target.closest('[data-sv-prev]')) prev();
      else if (e.target.closest('[data-sv-next]')) next();
    });
    show();
  }

  /* ---------- création d'une story ---------- */
  function create(onDone) {
    var S = Frikip.state, C = Frikip.config;
    if (!S.me) { U.toast('Connecte-toi pour publier une story'); if (C.loginUrl) setTimeout(function () { location.href = C.loginUrl; }, 700); return; }
    var input = document.createElement('input');
    input.type = 'file'; input.accept = 'image/*,video/*'; input.style.display = 'none';
    document.body.appendChild(input);
    input.addEventListener('change', function () {
      var file = input.files && input.files[0]; input.remove();
      if (!file) return;
      var isImg = /^image\//.test(file.type), url = URL.createObjectURL(file);
      U.sheet('Nouvelle story', '<div class="story-new-prev">' + (isImg ? '<img src="' + url + '" alt="">' : '<video src="' + url + '" muted autoplay loop playsinline></video>') + '</div>' +
        '<label class="sr-only" for="story-cap">Légende</label><input id="story-cap" class="input" maxlength="200" placeholder="Ajoute une légende (facultatif)">' +
        '<p class="hint" style="margin:8px 0 12px">Visible par tout le monde pendant 24 h.</p>' +
        '<button class="btn btn-green btn-block" type="button" data-story-send>Partager ma story</button>', function (sh) {
        sh.querySelector('[data-story-send]').addEventListener('click', function () {
          var b = this; b.disabled = true; b.textContent = 'Envoi…';
          api.postStory({ file: file, caption: sh.querySelector('#story-cap').value.trim(), onProgress: function (p) { b.textContent = 'Envoi… ' + Math.round(p * 100) + ' %'; } })
            .then(function () { U.closeSheet(); U.toast('Ta story est en ligne pour 24 h'); Frikip.bus.emit('stories:changed'); if (onDone) onDone(); })
            .catch(function (err) { b.disabled = false; b.textContent = 'Partager ma story'; U.toast(err.message || 'Envoi impossible'); });
        });
      });
    });
    input.click();
  }

  Frikip.stories = { open: open, create: create, close: close };
})();
