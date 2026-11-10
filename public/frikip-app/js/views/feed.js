/*
 * ACCUEIL — fil vidéo vertical façon TikTok.
 * Téléphone : plein écran, on glisse vers le haut. Ordinateur : vidéo 9:16 au centre, actions à droite.
 * Toucher = pause, double toucher = j'aime, ↑ ↓ au clavier pour changer de vidéo.
 */
window.Frikip = window.Frikip || {};
Frikip.views = Frikip.views || {};
(function () {
  var U = Frikip.ui, api = Frikip.api, S = Frikip.state;
  // Icônes pleines, grandes, façon TikTok (fil uniquement)
  var FILL = {
    heart: '<svg class="fi heart" viewBox="0 0 48 48" aria-hidden="true"><path d="M24 42.6c-.6 0-1.2-.2-1.7-.6C14 35.5 4 27.6 4 17.6 4 11.2 8.9 6 15.1 6c3.6 0 6.8 1.8 8.9 4.6C26.1 7.8 29.3 6 32.9 6 39.1 6 44 11.2 44 17.6c0 10-10 17.9-18.3 24.4-.5.4-1.1.6-1.7.6z"/></svg>',
    comment: '<svg class="fi" viewBox="0 0 48 48" aria-hidden="true"><path d="M24 5C12.4 5 3 12.8 3 22.5c0 5.4 2.9 10.2 7.5 13.4L9 43.2c-.2.9.8 1.6 1.6 1.1l8-4.8c1.7.4 3.5.6 5.4.6 11.6 0 21-7.8 21-17.5S35.6 5 24 5z"/><circle cx="14.5" cy="22.5" r="3" fill="#111"/><circle cx="24" cy="22.5" r="3" fill="#111"/><circle cx="33.5" cy="22.5" r="3" fill="#111"/></svg>',
    bookmark: '<svg class="fi bookmark" viewBox="0 0 48 48" aria-hidden="true"><path d="M11 4h26a3 3 0 0 1 3 3v36.5c0 1.2-1.4 1.9-2.4 1.2L24 35.3 10.4 44.7c-1 .7-2.4 0-2.4-1.2V7a3 3 0 0 1 3-3z"/></svg>',
    share: '<svg class="fi" viewBox="0 0 48 48" aria-hidden="true"><path d="M27.5 6.6c0-1.3 1.6-2 2.6-1.1l16 15.2c.6.6.6 1.6 0 2.2l-16 15.2c-1 .9-2.6.2-2.6-1.1v-7.5C15.6 29.6 8 33.5 3.6 41.3c-.5.9-1.9.5-1.8-.5C3.3 25.5 13 16 27.5 15V6.6z"/></svg>',
    plus: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6v12M6 12h12" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" fill="none"/></svg>',
    sound: '<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5L6 9H3v6h3l5 4z" fill="currentColor"/><path d="M16 9l5 6M21 9l-5 6"/></svg>',
    soundOn: '<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5L6 9H3v6h3l5 4z" fill="currentColor"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/></svg>'
  };
  var root, feedEl, observer, items = [], current = null, lastTap = 0, tapTimer = null, pendingFocus = null, pendingItem = null;

  // Vidéo « Découvrir » (YouTube) : image d'aperçu, le lecteur YouTube s'ouvre quand la vidéo est à l'écran
  function ytClipHtml(v) {
    var a = v.author;
    return '<section class="clip yt" data-id="' + v.id + '" aria-label="Vidéo YouTube de ' + U.esc(a.name) + '">' +
      '<div class="stage"><img class="yt-poster" src="' + U.esc(v.poster) + '" alt=""><div class="yt-slot" data-yt="' + U.esc(v.youtube) + '"></div>' +
        '<div class="clip-info yt-info"><span class="yt-chip">' + U.icon('play') + 'Découvrir · ' + U.esc(v.topic || 'YouTube') + '</span>' +
          '<div class="clip-author">' + (a.url ? '<a href="' + U.esc(a.url) + '" target="_blank" rel="noopener">' + U.esc(a.name) + '</a>' : U.esc(a.name)) + '</div>' +
          '<div class="clip-caption">' + U.esc(v.caption) + '</div></div></div>' +
      '<div class="clip-actions">' +
        '<button class="act" type="button" data-action="share" aria-label="Partager"><span class="ico">' + FILL.share + '</span><span>Partager</span></button>' +
        '<a class="act" href="https://www.youtube.com/watch?v=' + U.esc(v.youtube) + '" target="_blank" rel="noopener" aria-label="Ouvrir sur YouTube"><span class="ico">' + U.icon('play') + '</span><span>YouTube</span></a>' +
      '</div></section>';
  }

  function clipHtml(v) {
    if (v.source === 'youtube') return ytClipHtml(v);
    var a = v.author;
    var src = v.url || v.localUrl;
    var media = src
      ? '<video src="' + U.esc(src) + '"' + (v.poster ? ' poster="' + U.esc(v.poster) + '"' : '') + ' playsinline loop muted preload="metadata" aria-label="Vidéo de ' + U.esc(a.name) + '"></video>'
      : '<canvas role="img" aria-label="Vidéo de ' + U.esc(a.name) + ' : ' + U.esc(v.caption.slice(0, 80)) + '"></canvas>';
    return '<section class="clip" data-id="' + v.id + '" aria-label="Vidéo de ' + U.esc(a.name) + '">' +
      '<div class="stage" data-action="stage">' + media + '<div class="clip-shade"></div>' +
        (src ? '<button class="mute-chip" type="button" data-action="mute" aria-pressed="false" aria-label="Activer le son">' + FILL.sound + '<span>Touche pour le son</span></button>' : '') +
        '<div class="paused-mark" hidden><span>' + U.icon('play') + '</span></div>' +
        '<div class="clip-info">' +
          '<div class="clip-author"><button type="button" data-action="profile" data-user="' + a.id + '">@' + U.esc(a.handle) + '</button>' + (a.verified ? U.VERIF : '') + '</div>' +
          '<div class="clip-meta">' + U.esc(a.type) + (a.player ? ' · ' + U.esc(a.player.position) : '') + (a.club ? ' · ' + U.esc(a.club.name) : '') + ' · ' + U.ago(v.createdAt) + '</div>' +
          '<div class="clip-caption">' + U.caption(v.caption) + '</div>' +
          '<div class="clip-sound">' + U.icon('music') + '<span>' + U.esc(v.sound) + '</span></div>' +
          (v.credit ? '<div class="clip-credit">Vidéo : ' + U.esc(v.credit) + ' / Pexels</div>' : '') +
        '</div>' +
        '<div class="clip-progress" aria-hidden="true"><i></i></div>' +
      '</div>' +
      '<div class="clip-actions">' +
        '<div class="act-av"><button type="button" data-action="profile" data-user="' + a.id + '" aria-label="Profil de ' + U.esc(a.name) + '">' + U.avatar(a, 'md', true) + '</button>' +
          (a.isMe || a.isFollowing ? '' : '<button class="plus" type="button" data-action="follow-plus" data-user="' + a.id + '" aria-label="Suivre ' + U.esc(a.name) + '">' + FILL.plus + '</button>') + '</div>' +
        '<button class="act" type="button" data-action="like" aria-pressed="' + v.liked + '" aria-label="J’aime"><span class="ico">' + FILL.heart + '</span><span class="num" data-count="likes">' + U.n(v.likes) + '</span></button>' +
        '<button class="act" type="button" data-action="comments" aria-label="Commentaires"><span class="ico">' + FILL.comment + '</span><span class="num" data-count="comments">' + U.n(v.comments) + '</span></button>' +
        '<button class="act" type="button" data-action="save" aria-pressed="' + v.saved + '" aria-label="Enregistrer"><span class="ico">' + FILL.bookmark + '</span><span class="num" data-count="saves">' + U.n(v.saves) + '</span></button>' +
        '<button class="act" type="button" data-action="share" aria-label="Partager"><span class="ico">' + FILL.share + '</span><span class="num" data-count="shares">' + (v.shares ? U.n(v.shares) : 'Partager') + '</span></button>' +
        '<span class="disc" aria-hidden="true"><i style="background:' + U.esc(a.color || '#1FE064') + '">' + U.esc(a.name.charAt(0)) + '</i></span>' +
      '</div></section>';
  }

  function liveClipHtml(m) {
    return '<section class="clip" data-live="' + m.id + '" aria-label="Match en direct">' +
      '<div class="stage" data-action="match" data-match="' + m.id + '"><canvas role="img" aria-label="Match en direct"></canvas><div class="clip-shade"></div>' +
        '<button class="live-card" type="button" data-action="match" data-match="' + m.id + '">' + U.crest(m.homeClub, 34) +
          '<span class="tm"><span><span class="pill-live">Direct</span> <span class="num" data-minute>' + m.minute + '’</span></span><small>' + U.esc(m.comp) + '</small></span>' +
          '<span class="sc num" data-score>' + m.hs + '–' + m.as + '</span>' + U.crest(m.awayClub, 34) + '</button>' +
        '<div class="clip-info"><div class="clip-author">' + U.esc(m.homeClub.name) + ' – ' + U.esc(m.awayClub.name) + '</div>' +
          '<div class="clip-caption">Match en cours au ' + U.esc(m.venue) + '. Ouvre le match pour le fil des actions et les statistiques.</div>' +
          '<div><button class="btn btn-green btn-sm" type="button" data-action="match" data-match="' + m.id + '">Suivre le match</button></div></div>' +
      '</div><div class="clip-actions"></div></section>';
  }

  function render() {
    root.innerHTML =
      '<div class="feed-logo"><a class="iconbtn reels-back" href="#accueil" aria-label="Retour à l’accueil">' + U.icon('back') + '</a></div>' +
      '<div class="feed-tabs" role="group" aria-label="Fil">' +
        '<button type="button" data-tab="abonnements" aria-pressed="' + (S.feedTab === 'abonnements') + '">Abonnements</button>' +
        '<button type="button" data-tab="pour-toi" aria-pressed="' + (S.feedTab === 'pour-toi') + '">Pour toi</button>' +
      '</div>' +
      '<div class="feed-top-actions"><a class="iconbtn" href="#explorer" aria-label="Rechercher">' + U.icon('search') + '</a>' +
        '<a class="iconbtn bell-link" href="#notifications" aria-label="Notifications">' + U.icon('bell') + '<span class="dot-badge" data-badge="notifications" hidden></span></a></div>' +
      '<div class="feed" id="feed" tabindex="-1" aria-label="Vidéos"></div>';
    feedEl = root.querySelector('#feed');
    load();
  }

  function load() {
    Frikip.scene.stop();
    feedEl.innerHTML = '';
    Promise.all([api.feed(S.feedTab, 1, pendingFocus), S.feedTab === 'pour-toi' ? api.matches('direct') : Promise.resolve([])]).then(function (r) {
      items = r[0].items;
      // vidéo ouverte depuis l'accueil, une story ou Explorer : toujours en premier
      if (pendingItem) { items = [pendingItem].concat(items.filter(function (x) { return x.id !== pendingItem.id; })); pendingItem = null; }
      if (!items.length && S.feedTab === 'pour-toi') {
        feedEl.innerHTML = '<div class="empty-feed"><div><b>Pas encore de vidéo.</b><span style="color:var(--muted)">Sois le premier à publier une vidéo foot.</span><a class="btn btn-green" href="#publier">Publier une vidéo</a></div></div>';
        return;
      }
      if (!items.length) {
        feedEl.innerHTML = '<div class="empty-feed"><div><b>Rien de neuf dans tes abonnements.</b><span style="color:var(--muted)">Abonne-toi à des joueurs, des clubs et des créateurs depuis « Pour toi ».</span>' +
          '<button class="btn btn-green" type="button" data-tab="pour-toi">Découvrir « Pour toi »</button></div></div>';
        return;
      }
      var html = items.map(clipHtml);
      if (r[1].length && html.length > 2) html.splice(2, 0, liveClipHtml(r[1][0]));
      feedEl.innerHTML = html.join('');
      feedEl.scrollTop = 0;
      observe();
      if (pendingFocus) { var target = feedEl.querySelector('[data-id="' + pendingFocus + '"]'); pendingFocus = null; if (target) target.scrollIntoView(); }
    });
  }

  function videoFor(el) { var id = el.getAttribute('data-id'); for (var i = 0; i < items.length; i++) if (items[i].id === id) return items[i]; return null; }

  var activeSince = 0;
  function leave(el) {
    var ov = el.querySelector('video'); if (ov) ov.pause();
    var slot = el.querySelector('[data-yt]'); if (slot) slot.innerHTML = '';
    var v = videoFor(el);
    if (v && v.source !== 'youtube' && activeSince && api.watchTime) api.watchTime(v, (Date.now() - activeSince) / 1000);
  }
  function activate(el) {
    if (current === el) return;
    if (current) leave(current);
    current = el; activeSince = Date.now();
    var slot = el.querySelector('[data-yt]');
    if (slot) {
      Frikip.scene.stop();
      var yt = slot.getAttribute('data-yt');
      slot.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + encodeURIComponent(yt) + '?autoplay=1&mute=1&playsinline=1&rel=0&modestbranding=1&loop=1&playlist=' + encodeURIComponent(yt) + '" title="Vidéo YouTube" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>';
      var dv = videoFor(el); if (dv && dv.dbId && api.discoverPlay) api.discoverPlay(dv.dbId);
      return;
    }
    var seenV = videoFor(el);
    if (seenV && api.view) setTimeout(function () { if (current === el) api.view(seenV); }, 2000);
    var mark = el.querySelector('.paused-mark'); if (mark) mark.hidden = true;
    if (el.hasAttribute('data-live')) {
      Frikip.api.match(el.getAttribute('data-live')).then(function (m) { Frikip.scene.play(el.querySelector('canvas'), { id: m.id, scene: 'pitch', hue: m.homeClub.colors[0] }); });
      return;
    }
    var v = videoFor(el), bar = el.querySelector('.clip-progress i'), vid = el.querySelector('video');
    if (vid) { Frikip.scene.stop(); vid.play().catch(function () {}); vid.ontimeupdate = function () { bar.style.width = (vid.currentTime / (vid.duration || 1) * 100) + '%'; }; return; }
    Frikip.scene.play(el.querySelector('canvas'), v, function (t) { bar.style.width = ((t % v.duration) / v.duration * 100) + '%'; });
  }

  function observe() {
    if (observer) observer.disconnect();
    current = null;
    observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting && e.intersectionRatio > .6) activate(e.target); });
    }, { root: feedEl, threshold: [.6] });
    feedEl.querySelectorAll('.clip').forEach(function (c) {
      observer.observe(c);
      var v = videoFor(c), cv = c.querySelector('canvas');
      if (v && cv) Frikip.scene.still(cv, v);
    });
  }

  function togglePause(clip) {
    var vid = clip.querySelector('video'), mark = clip.querySelector('.paused-mark');
    var paused = vid ? !vid.paused : Frikip.scene.isPlaying();
    if (vid) { if (paused) vid.pause(); else vid.play().catch(function () {}); }
    else if (paused) Frikip.scene.pause(); else Frikip.scene.resume();
    if (mark) mark.hidden = !paused;
  }

  function like(clip, x, y, force) {
    var v = videoFor(clip), btn = clip.querySelector('[data-action="like"]');
    if (x != null) {
      var b = document.createElement('div'); b.className = 'burst'; b.style.left = x + 'px'; b.style.top = y + 'px';
      b.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z"/></svg>';
      clip.querySelector('.stage').appendChild(b); setTimeout(function () { b.remove(); }, 750);
    }
    if (force && v.liked) return;
    api.toggleLike(v.id).then(function (r) {
      v.liked = r.liked; v.likes = r.likes;
      btn.setAttribute('aria-pressed', r.liked); btn.querySelector('[data-count]').textContent = U.n(r.likes);
    });
  }

  function openComments(clip) {
    var v = videoFor(clip);
    var body = '<div data-list><p style="color:var(--muted)">Chargement…</p></div>';
    U.sheet(U.n(v.comments) + ' commentaires', body + '<form class="composer" data-form style="padding:12px 0 0;border:0"><label class="sr-only" for="cmt-input">Ton commentaire</label>' +
      '<input id="cmt-input" maxlength="300" placeholder="Ajoute un commentaire…" autocomplete="off"><button class="send" type="submit" aria-label="Publier le commentaire">' + U.icon('send') + '</button></form>', function (sh) {
      var list = sh.querySelector('[data-list]');
      var row = function (c) {
        return '<div class="cmt">' + U.avatar(c.author, 'sm') + '<div class="tx"><b>' + U.name(c.author) + (c.pinned ? ' <span class="pinned">Épinglé</span>' : '') + '</b>' + U.esc(c.text) + '<small> · ' + U.ago(c.createdAt) + '</small></div>' +
          '<button class="lk" type="button" data-cmt-like="' + c.id + '" aria-pressed="' + !!c.liked + '" aria-label="Aimer ce commentaire">' + U.icon('heart') + '<span class="num">' + U.n(c.likes) + '</span></button></div>';
      };
      api.comments(v.id).then(function (cs) { list.innerHTML = cs.length ? cs.map(row).join('') : '<p style="color:var(--muted)">Sois le premier à commenter.</p>'; });
      list.addEventListener('click', function (e) {
        var b = e.target.closest('[data-cmt-like]'); if (!b) return;
        api.toggleCommentLike(b.getAttribute('data-cmt-like')).then(function (r) { b.setAttribute('aria-pressed', r.liked); b.querySelector('span').textContent = U.n(r.likes); });
      });
      sh.querySelector('[data-form]').addEventListener('submit', function (e) {
        e.preventDefault();
        var inp = sh.querySelector('input'), text = inp.value.trim(); if (!text) return;
        inp.value = '';
        api.addComment(v.id, text).then(function (c) {
          if (list.querySelector('p')) list.innerHTML = '';
          list.insertAdjacentHTML('afterbegin', row(c));
          v.comments++; clip.querySelector('[data-count="comments"]').textContent = U.n(v.comments);
          sh.querySelector('h3').textContent = U.n(v.comments) + ' commentaires';
        });
      });
    });
  }

  function openShare(clip) {
    var v = videoFor(clip), link = Frikip.config.shareBase + v.id;
    U.sheet('Partager', '<div class="share-grid">' +
      '<button type="button" data-ch="whatsapp"><span>' + U.icon('whatsapp') + '</span>WhatsApp</button>' +
      '<button type="button" data-ch="lien"><span>' + U.icon('link') + '</span>Copier le lien</button>' +
      '<button type="button" data-ch="message"><span>' + U.icon('msg') + '</span>Message</button>' +
      '<button type="button" data-ch="signaler"><span>' + U.icon('flag') + '</span>Signaler</button></div>' +
      '<p class="hint" style="margin:8px 0 0">Lien de la vidéo : <span style="user-select:all">' + link + '</span></p>', function (sh) {
      sh.addEventListener('click', function (e) {
        var b = e.target.closest('[data-ch]'); if (!b) return;
        var ch = b.getAttribute('data-ch');
        if (ch === 'signaler') { U.closeSheet(); if (!Frikip.state.me) { Frikip.bus.emit('auth:required'); return; } api.report('video', v.id, 'Vidéo signalée depuis le fil').then(function () { U.toast('Merci, la vidéo est envoyée à la modération.'); }).catch(function (x) { U.toast(x.message); }); return; }
        if (ch === 'lien' && navigator.clipboard) navigator.clipboard.writeText(link).catch(function () {});
        api.share(v.id, ch).then(function (r) { v.shares = r.shares; clip.querySelector('[data-count="shares"]').textContent = U.n(r.shares); });
        U.closeSheet();
        U.toast(ch === 'lien' ? 'Lien copié' : ch === 'whatsapp' ? 'Partage WhatsApp prêt' : 'Choisis une conversation dans Messages');
        if (ch === 'message') location.hash = 'messages';
      });
    });
  }

  function onClick(e) {
    var t = e.target.closest('[data-action],[data-tab]'); if (!t) return;
    var clip = t.closest('.clip');
    if (t.hasAttribute('data-tab')) {
      S.feedTab = t.getAttribute('data-tab'); Frikip.prefs.set('feedTab', S.feedTab);
      root.querySelectorAll('.feed-tabs button').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-tab') === S.feedTab); });
      load(); return;
    }
    var act = t.getAttribute('data-action');
    if (act === 'stage') {
      var now = Date.now(), rect = t.getBoundingClientRect(), x = e.clientX - rect.left, y = e.clientY - rect.top;
      if (now - lastTap < 280) { clearTimeout(tapTimer); lastTap = 0; like(clip, x, y, true); return; }
      lastTap = now; tapTimer = setTimeout(function () { togglePause(clip); }, 280);
      return;
    }
    if (act === 'like') like(clip);
    else if (act === 'comments') openComments(clip);
    else if (act === 'share') openShare(clip);
    else if (act === 'mute') {
      var vd = clip.querySelector('video'); if (!vd) return;
      vd.muted = !vd.muted;
      feedEl.querySelectorAll('video').forEach(function (o) { o.muted = vd.muted; });
      feedEl.querySelectorAll('[data-action="mute"]').forEach(function (b) {
        b.setAttribute('aria-pressed', String(!vd.muted)); b.setAttribute('aria-label', vd.muted ? 'Activer le son' : 'Couper le son');
        b.innerHTML = vd.muted ? FILL.sound + '<span>Touche pour le son</span>' : FILL.soundOn;
      });
    }
    else if (act === 'save') {
      var v = videoFor(clip);
      api.toggleSave(v.id).then(function (r) { v.saved = r.saved; t.setAttribute('aria-pressed', r.saved); t.querySelector('[data-count]').textContent = U.n(r.saves); U.toast(r.saved ? 'Ajouté à tes favoris' : 'Retiré de tes favoris'); });
    } else if (act === 'follow-plus') {
      api.toggleFollow(t.getAttribute('data-user')).then(function (r) {
        feedEl.querySelectorAll('[data-action="follow-plus"][data-user="' + t.getAttribute('data-user') + '"]').forEach(function (b) { b.remove(); });
        items.forEach(function (v) { if (v.author.id === t.getAttribute('data-user')) v.author.isFollowing = r.following; });
        U.toast('Abonné');
      });
    }
  }

  function onKey(e) {
    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'l') return;
    if (e.target.closest('input, textarea')) return;
    var clips = Array.prototype.slice.call(feedEl.querySelectorAll('.clip')), i = clips.indexOf(current);
    if (e.key === 'l' && current && !current.hasAttribute('data-live')) { like(current); return; }
    var next = clips[i + (e.key === 'ArrowDown' ? 1 : -1)];
    if (next) { e.preventDefault(); next.scrollIntoView({ behavior: 'smooth' }); }
  }

  Frikip.views.reels = {
    mount: function (el) { root = el; S.feedTab = pendingFocus ? 'pour-toi' : Frikip.prefs.get('feedTab', 'pour-toi'); render(); el.addEventListener('click', onClick); el.addEventListener('keydown', onKey); },
    show: function () { if (feedEl && feedEl.querySelector('.clip')) observe(); if (feedEl) feedEl.focus({ preventScroll: true }); },
    hide: function () { Frikip.scene.stop(); if (current) { leave(current); current = null; } },
    reload: function () { if (root) render(); },
    focusVideo: function (id, item) { pendingFocus = id; pendingItem = item || null; S.feedTab = 'pour-toi'; if (root) render(); },
    tick: function (ids) {
      if (!feedEl) return;
      ids.forEach(function (id) {
        var card = feedEl.querySelector('[data-live="' + id + '"]'); if (!card) return;
        api.match(id).then(function (m) { var mn = card.querySelector('[data-minute]'); if (mn) mn.textContent = m.status === 'live' ? m.minute + '’' : 'Terminé'; });
      });
    }
  };
})();
