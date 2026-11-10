/*
 * EXPLORER — recherche (comptes, clubs, vidéos, #hashtags), catégories, tendances,
 * vidéos populaires et comptes à suivre.
 */
window.Frikip = window.Frikip || {};
Frikip.views = Frikip.views || {};
(function () {
  var U = Frikip.ui, api = Frikip.api;
  var root, q = '', filter = 'tout', timer = null;
  var FILTERS = [['tout', 'Tout'], ['joueurs', 'Joueurs'], ['acteurs', 'Acteurs du foot'], ['clubs', 'Clubs'], ['videos', 'Vidéos'], ['decouvrir', 'Découvrir'], ['reels', 'Reels']];
  var L = function () { return Frikip.config.links || {}; };
  // Tuiles : [titre, icône, couleur, destination]  (#… = écran de l'app, url: = page du site, q:/tag: = recherche)
  var CATS = [
    ['Vidéos', 'film', 'c-red', '#reels'],
    ['Joueurs', 'user', 'c-blue', 'q:joueurs'],
    ['Clubs', 'shield', 'c-orange', 'q:clubs'],
    ['Lives', 'reels', 'c-pink', 'lives'],
    ['Matchs', 'ball', 'c-violet', '#matchs'],
    ['Découvrir', 'play', 'c-yt', 'q:decouvrir'],
    ['Défis', 'trophy', 'c-green', 'url:challenges'],
    ['Entraînement', 'dumbbell', 'c-sky', 'url:coaching'],
    ['Acteurs du foot', 'users', 'c-teal', 'q:acteurs']
  ];
  Frikip.cache = Frikip.cache || {};
  function remember(list) { (list || []).forEach(function (v) { Frikip.cache[v.id] = v; }); }

  function thumbs(list) {
    remember(list);
    return '<div class="grid3">' + list.map(function (v) {
      return '<button class="thumb" type="button" data-action="open-video" data-video="' + v.id + '" aria-label="Vidéo de ' + U.esc(v.author.name) + '">' +
        U.thumbMedia(v) + '<span class="tt">' + U.esc(v.caption.replace(/#[\p{L}\p{N}_]+/gu, '').trim()) + '</span>' +
        '<span class="tv">' + U.icon('play') + '<span class="num">' + U.n(v.views) + '</span></span></button>';
    }).join('') + '</div>';
  }
  function people(list) {
    return list.map(function (u) {
      return '<div class="person"><button type="button" data-action="profile" data-user="' + u.id + '" aria-label="Profil de ' + U.esc(u.name) + '">' + U.avatar(u, 'md') + '</button>' +
        '<button class="who" type="button" data-action="profile" data-user="' + u.id + '"><b>' + U.name(u) + '</b><small>@' + U.esc(u.handle) + ' · ' + U.esc(u.type) + ' · ' + U.n(u.followers) + ' abonnés</small></button>' +
        U.followBtn(u, true) + '</div>';
    }).join('');
  }
  function paintThumbs(list) {
    list.forEach(function (v) { root.querySelectorAll('[data-thumb="' + v.id + '"]').forEach(function (c) { Frikip.scene.still(c, v); }); });
  }

  function render() {
    root.innerHTML =
      '<header class="topbar"><h1>Explorer</h1><a class="iconbtn" href="#notifications" aria-label="Notifications">' + U.icon('bell') + '<span class="dot-badge" data-badge="notifications" hidden></span></a></header>' +
      '<div class="page"><h1 class="page-title">Explorer</h1>' +
      '<form class="search" role="search" data-form>' + U.icon('search') + '<label class="sr-only" for="x-q">Rechercher</label>' +
      '<input id="x-q" type="search" value="' + U.esc(q) + '" placeholder="Rechercher un joueur, un club, un #hashtag…" autocomplete="off" enterkeyhint="search"></form>' +
      '<div class="chip-row" role="group" aria-label="Filtrer" style="margin-top:12px">' + FILTERS.map(function (f) {
        return '<button class="chip" type="button" data-filter="' + f[0] + '" aria-pressed="' + (filter === f[0]) + '">' + f[1] + '</button>';
      }).join('') + '</div><div data-body></div></div>';
    draw();
  }

  function drawDiscover(body, topic) {
    api.discover(topic || '').then(function (d) {
      remember(d.items);
      body.innerHTML = (d.topics.length ? '<div class="chip-row" role="group" aria-label="Thèmes" style="margin-top:14px"><button class="chip" type="button" data-topic="" aria-pressed="' + !topic + '">Tous</button>' +
          d.topics.map(function (t) { return '<button class="chip" type="button" data-topic="' + U.esc(t.key) + '" aria-pressed="' + (topic === t.key) + '">' + U.esc(t.label) + '</button>'; }).join('') + '</div>' : '') +
        (d.items.length ? '<div class="section-h"><h2>Découvrir</h2><span class="hint">Vidéos foot YouTube</span></div>' + thumbs(d.items)
          : '<p class="no-result">Les vidéos « Découvrir » arrivent dès que la clé YouTube est ajoutée dans le site.</p>');
    });
  }
  function draw() {
    var body = root.querySelector('[data-body]');
    if (filter === 'decouvrir') { drawDiscover(body, ''); return; }
    if (q.trim() || filter !== 'tout') {
      api.search(q, filter).then(function (r) {
        var html = '';
        if (r.users.length) html += '<div class="section-h"><h2>Comptes</h2></div>' + people(r.users);
        if (r.clubs.length) html += '<div class="section-h"><h2>Clubs</h2></div>' + r.clubs.map(function (c) {
          return '<div class="person">' + U.crest(c, 40) + '<div class="who"><b>' + U.esc(c.name) + (c.verified ? ' ' + U.VERIF : '') + '</b><small>' + U.esc(c.city) + (c.followers ? ' · ' + U.n(c.followers) + ' abonnés' : '') + '</small></div>' +
            (c.url ? '<a class="btn btn-sm btn-ghost" href="' + U.esc(c.url) + '">Voir</a>' : '<span class="hint">Club</span>') + '</div>';
        }).join('');
        if (r.videos.length) html += '<div class="section-h"><h2>Vidéos</h2><span class="hint num">' + r.videos.length + '</span></div>' + thumbs(r.videos);
        body.innerHTML = html || '<p class="no-result">' + (q.trim() ? 'Aucun résultat pour « ' + U.esc(q) + ' ». Essaie un nom, une ville ou un #hashtag.' : 'Rien à afficher dans cette catégorie pour l’instant.') + '</p>';
        paintThumbs(r.videos);
      });
      return;
    }
    api.explore().then(function (r) {
      body.innerHTML =
        '<div class="section-h"><h2>Catégories</h2></div><div class="cats">' + CATS.map(function (c) {
          return '<button class="tile ' + c[2] + '" type="button" data-go="' + U.esc(c[3]) + '"><span class="tile-ic">' + U.icon(c[1]) + '</span><b>' + c[0] + '</b></button>';
        }).join('') + '</div>' +
        (r.trends.length ? '<div class="section-h"><h2>Tendances</h2><button type="button" data-filter-go="videos">Voir tout</button></div><div class="trend-cards">' + r.trends.map(function (t) {
          var v = r.popular.filter(function (x) { return x.caption.toLowerCase().indexOf(t.tag.toLowerCase()) > -1; })[0] || r.popular[0];
          return '<button class="trend-card" type="button" data-action="tag" data-tag="' + U.esc(t.tag) + '"><span class="tc-media">' + (v ? U.thumbMedia(v) : '') + '</span>' +
            '<b>' + U.esc(t.tag) + '</b><small>' + U.n(t.posts) + ' publication' + (t.posts > 1 ? 's' : '') + '</small></button>';
        }).join('') + '</div>' : '') +
        '<div class="section-h"><h2>Recommandés</h2></div>' + people(r.suggested) +
        (r.discover && r.discover.length ? '<div class="section-h"><h2>Découvrir</h2><button type="button" data-filter-go="decouvrir">Voir tout</button></div>' + thumbs(r.discover.slice(0, 6)) : '') +
        (r.popular.length ? '<div class="section-h"><h2>Vidéos populaires</h2></div>' + thumbs(r.popular) : '');
      paintThumbs(r.popular);
      root.querySelectorAll('.trend-card canvas[data-thumb]').forEach(function (c) { var v = r.popular.filter(function (x) { return x.id === c.getAttribute('data-thumb'); })[0]; if (v) Frikip.scene.still(c, v); });
    });
  }

  function onClick(e) {
    var fg = e.target.closest('[data-filter-go]');
    if (fg) { filter = fg.getAttribute('data-filter-go'); render(); return; }
    var f = e.target.closest('[data-filter]');
    if (f && f.getAttribute('data-filter') === 'reels') { location.hash = 'reels'; return; }
    var tp = e.target.closest('[data-topic]');
    if (tp) { drawDiscover(root.querySelector('[data-body]'), tp.getAttribute('data-topic')); return; }
    if (f) { filter = f.getAttribute('data-filter'); root.querySelectorAll('[data-filter]').forEach(function (b) { b.setAttribute('aria-pressed', b === f); }); draw(); return; }
    var g = e.target.closest('[data-go]');
    if (g) {
      var go = g.getAttribute('data-go');
      if (go.charAt(0) === '#') { location.hash = go.slice(1); return; }
      if (go.indexOf('tag:') === 0) { Frikip.views.explorer.search(go.slice(4), 'videos'); return; }
      if (go === 'lives') { Frikip.views.matchs.showLives(); location.hash = 'matchs'; return; }
      if (go.indexOf('url:') === 0) { var u = L()[go.slice(4)]; if (u) location.href = u; else U.toast('Disponible sur le site'); return; }
      if (go === 'q:acteurs') { Frikip.views.explorer.search('', 'acteurs'); return; }
      if (go === 'q:decouvrir') { Frikip.views.explorer.search('', 'decouvrir'); return; }
      if (go === 'q:joueurs') { Frikip.views.explorer.search('', 'joueurs'); return; }
      if (go === 'q:clubs') { Frikip.views.explorer.search('', 'clubs'); }
    }
  }

  Frikip.views.explorer = {
    mount: function (el) {
      root = el; render();
      el.addEventListener('click', onClick);
      el.addEventListener('input', function (e) { if (e.target.id !== 'x-q') return; q = e.target.value; clearTimeout(timer); timer = setTimeout(draw, 180); });
      el.addEventListener('submit', function (e) { e.preventDefault(); draw(); });
    },
    show: function () {},
    search: function (text, f) {
      q = text; filter = f || 'tout';
      if (location.hash !== '#explorer') location.hash = 'explorer';
      render();
    }
  };
})();
