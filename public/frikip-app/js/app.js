/*
 * DÉMARRAGE — coque de l'application, navigation (adresse #accueil, #explorer…),
 * actions communes à tous les écrans (profil, suivre, hashtag, match), compteurs non lus.
 */
window.Frikip = window.Frikip || {};
(function () {
  var U = Frikip.ui, api = Frikip.api, S = Frikip.state, V = Frikip.views;

  Frikip.views.logoSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12l-1.6 4H10.4l-.7 2.6h5.6L13.7 13.6H8.6L6.9 21H3z" fill="#03140A"/><circle cx="18.5" cy="17.5" r="3" fill="#03140A"/></svg>';
  // Logo façon maquette : ballon + éclair vert
  Frikip.views.brandSvg = '<svg class="brand-ball" viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="17" fill="#fff"/><path d="M20 11l7 5-2.7 8.3h-8.6L13 16z" fill="#0a0d0b"/><path d="M20 3v8M27 16l7.5-3M24.3 24.3l4.7 7M15.7 24.3L11 31.3M13 16l-7.5-3" stroke="#0a0d0b" stroke-width="2.2"/><path d="M3 23c6-1 12 2 17 7 5-6 11-9 17-9" stroke="#1FE064" stroke-width="3.4" fill="none" stroke-linecap="round"/></svg>';
  var logo = '<a class="brand" href="#accueil" aria-label="Frikip, accueil">' + Frikip.views.brandSvg + '<span><b>FRIKIP</b><small>Le réseau foot</small></span></a>';

  var ROUTES = ['accueil', 'reels', 'explorer', 'publier', 'matchs', 'messages', 'profil', 'compte', 'notifications', 'menu'];
  var NO_RAIL = ['messages', 'publier'];
  var PRIVATE = ['publier', 'messages', 'profil', 'notifications', 'menu'];   // écrans réservés aux comptes connectés
  var C = Frikip.config;

  function guestGate(el, name) {
    var what = { publier: 'publier une vidéo', messages: 'lire et envoyer des messages', profil: 'voir ton profil', notifications: 'voir tes notifications', menu: 'gérer ton compte' }[name];
    el.innerHTML = '<div class="empty-feed"><div>' + logo + '<b style="font-size:20px">Connecte-toi pour ' + what + '</b>' +
      '<span style="color:var(--muted)">Crée ton compte en 30 secondes avec ton numéro de téléphone.</span>' +
      '<a class="btn btn-green" href="' + U.esc(C.loginUrl || '#accueil') + '">Se connecter ou s’inscrire</a></div></div>';
  }
  var NAV = [['accueil', 'Accueil', 'home'], ['reels', 'Reels', 'film'], ['explorer', 'Explorer', 'search'], ['matchs', 'Matchs', 'ball'], ['messages', 'Messages', 'msg', 'messages'], ['notifications', 'Notifications', 'bell', 'notifications'], ['profil', 'Profil', 'user']];
  var TABS = [['accueil', 'Accueil', 'home'], ['explorer', 'Explorer', 'search'], ['publier', 'Publier', 'plus'], ['matchs', 'Matchs', 'ball'], ['messages', 'Messages', 'msg', 'messages'], ['profil', 'Profil', 'user']];

  function shell() {
    var app = document.getElementById('app');
    app.innerHTML =
      '<nav class="side" aria-label="Navigation principale">' + logo +
        NAV.map(function (n) { return '<a class="nav-a" href="#' + n[0] + '" data-nav="' + n[0] + '">' + U.icon(n[2]) + n[1] + (n[3] ? '<span class="dot-badge num" data-badge="' + n[3] + '" hidden></span>' : '') + '</a>'; }).join('') +
        '<a class="btn btn-green" href="#publier">' + U.icon('plus') + 'Publier une vidéo</a>' +
        '<div class="side-foot" data-side-me></div>' +
      '</nav>' +
      '<main class="main" id="main">' + ROUTES.map(function (r) { return '<section class="view" data-view="' + r + '" hidden></section>'; }).join('') + '</main>' +
      '<aside class="rail" aria-label="À la une" data-rail></aside>' +
      '<nav class="tabbar" aria-label="Onglets">' + TABS.map(function (t) {
        if (t[0] === 'publier') return '<a class="tab-plus" href="#publier" data-nav="publier" aria-label="Publier une vidéo"><span>' + U.icon('plus') + '</span></a>';
        return '<a class="tab" href="#' + t[0] + '" data-nav="' + t[0] + '">' + U.icon(t[2]) + t[1] + (t[3] ? '<span class="dot-badge num" data-badge="' + t[3] + '" hidden></span>' : '') + '</a>';
      }).join('') + '</nav>';
  }

  // ---------------------------------------------------------------- navigation
  var mounted = {};
  function route() {
    var name = (location.hash || '').replace('#', '') || 'accueil';
    // lien partagé d'une vidéo : /app#v=<id>
    if (name.indexOf('v=') === 0) { var shared = decodeURIComponent(name.slice(2)); V.reels.focusVideo(shared); history.replaceState(null, '', '#reels'); name = 'reels'; }
    if (ROUTES.indexOf(name) < 0) name = 'accueil';
    if (name === 'compte' && !S.profileId) name = 'accueil';
    U.closeSheet();   // une feuille ouverte (commentaires, match…) se ferme quand on change d'écran
    var prev = S.view;
    if (prev && prev !== name && V[prev] && V[prev].hide) V[prev].hide();
    S.view = name;
    document.querySelectorAll('.view').forEach(function (v) { v.hidden = v.getAttribute('data-view') !== name; });
    var el = document.querySelector('[data-view="' + name + '"]');
    if (!S.me && PRIVATE.indexOf(name) > -1) { guestGate(el, name); mounted[name] = false; }
    else {
      if (!mounted[name]) { V[name].mount(el); mounted[name] = true; }
      if (V[name].show) V[name].show();
    }
    document.querySelectorAll('[data-nav]').forEach(function (a) {
      var on = a.getAttribute('data-nav') === name || (name === 'reels' && a.getAttribute('data-nav') === 'accueil' && a.classList.contains('tab')) || (name === 'menu' && a.getAttribute('data-nav') === 'profil');
      if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
    });
    document.getElementById('app').classList.toggle('no-rail', NO_RAIL.indexOf(name) > -1);
    document.body.classList.toggle('in-reels', name === 'reels');
    document.title = { accueil: 'Frikip', reels: 'Reels · Frikip', menu: 'Menu · Frikip', explorer: 'Explorer · Frikip', publier: 'Publier · Frikip', matchs: 'Matchs · Frikip', messages: 'Messages · Frikip', profil: 'Mon profil · Frikip', compte: 'Profil · Frikip', notifications: 'Notifications · Frikip' }[name];
    updateCounts();
  }

  // ------------------------------------------------------------ « + » : que veux-tu partager ?
  Frikip.views.create = function () {
    var me = S.me, Lk = C.links || {};
    if (!me) { U.toast('Connecte-toi pour publier'); if (C.loginUrl) setTimeout(function () { location.href = C.loginUrl; }, 700); return; }
    var items = [];
    if (me.canPublish) items.push(['#publier', 'upload', 'Publier une vidéo', 'Un geste, un but, un entraînement…', 'c-green']);
    items.push([Lk.liveGo || '#matchs', 'film', 'Lancer un live', me.liveRefusal || 'Filme et commente un match en direct depuis ton téléphone.', 'c-red']);
    items.push(['#matchs', 'msg', 'Commenter un match', 'Réagis dans le salon d’un match avec les autres supporters.', 'c-violet']);
    if (me.role === 'recruiter') {
      items.push([Lk.events, 'calendar', 'Publier une détection', 'Annonce une journée de détection ou un essai.', 'c-orange']);
      items.push([Lk.offers, 'flag', 'Publier une offre', 'Cherche un joueur pour un poste précis.', 'c-blue']);
      items.push([Lk.liveBroadcast, 'ball', 'Diffuser un match de mon club', 'Avec une caméra ou un logiciel de diffusion (OBS).', 'c-sky']);
    }
    if (me.role === 'fan') items.push([Lk.proche, 'user', 'Mettre en avant un proche', 'Crée le profil de joueur d’un frère, d’un fils, d’un ami… et publie ses vidéos.', 'c-blue']);
    U.sheet('Que veux-tu partager ?', '<div class="create-list">' + items.filter(function (i) { return i[0]; }).map(function (i) {
      return '<a class="create-item" href="' + U.esc(i[0]) + '"><span class="tile-ic ' + i[4] + '">' + U.icon(i[1]) + '</span><span><b>' + i[2] + '</b><small>' + U.esc(i[3]) + '</small></span></a>';
    }).join('') + '</div>', function (sh) { sh.addEventListener('click', function (e) { if (e.target.closest('a[href^="#"]')) U.closeSheet(); }); });
  };
  document.addEventListener('click', function (e) {
    var plus = e.target.closest('[data-nav="publier"], [data-create]');
    if (!plus) return;
    if (!S.me || !S.me.canPublish || plus.hasAttribute('data-create')) { e.preventDefault(); e.stopPropagation(); Frikip.views.create(); }
  }, true);

  // ------------------------------------------------------------ colonne droite
  function rail() {
    var r = document.querySelector('[data-rail]');
    Promise.all([api.matches('tout'), api.explore()]).then(function (x) {
      var ms = x[0].filter(function (m) { return m.status !== 'finished'; }).slice(0, 4), ex = x[1];
      r.innerHTML =
        '<div class="section-h"><h2>Matchs du jour</h2><a href="#matchs">Voir tout</a></div>' +
        (ms.length ? '' : '<p class="hint" style="margin:0 0 6px">Aucun match en cours ou à venir aujourd’hui.</p>') +
        ms.map(function (m) {
          var live = m.status === 'live';
          return '<button class="mini-match" type="button" data-action="match" data-match="' + m.id + '"><span>' + U.crest(m.homeClub, 26) + '<small>' + U.esc(m.homeClub.short) + '</small></span>' +
            '<span>' + (live ? '<b class="num">' + m.hs + '–' + m.as + '</b><small class="on num" data-rail-min="' + m.id + '">' + m.minute + '’ direct</small>' : '<b class="num">' + U.esc(m.kickoff) + '</b><small>' + U.esc(m.day) + '</small>') + '</span>' +
            '<span>' + U.crest(m.awayClub, 26) + '<small>' + U.esc(m.awayClub.short) + '</small></span></button>';
        }).join('') +
        '<div class="section-h"><h2>Tendances</h2><a href="#explorer">Explorer</a></div>' +
        ex.trends.slice(0, 5).map(function (t, i) {
          return '<button class="trend" type="button" data-action="tag" data-tag="' + U.esc(t.tag) + '" style="width:100%;margin-bottom:6px"><span class="rk num">' + (i + 1) + '</span><div><b>' + U.esc(t.tag) + '</b><small>' + t.posts + ' publication' + (t.posts > 1 ? 's' : '') + '</small></div></button>';
        }).join('') +
        '<div class="section-h"><h2>Suggestions</h2></div>' +
        ex.suggested.slice(0, 4).map(function (u) {
          return '<div class="person"><button type="button" data-action="profile" data-user="' + u.id + '" aria-label="Profil de ' + U.esc(u.name) + '">' + U.avatar(u, 'sm') + '</button>' +
            '<button class="who" type="button" data-action="profile" data-user="' + u.id + '"><b>' + U.name(u) + '</b><small>' + U.esc(u.type) + ' · ' + U.n(u.followers) + '</small></button>' + U.followBtn(u, true) + '</div>';
        }).join('') +
        (C.mode === 'demo' ? '<p class="hint" style="margin-top:18px"><span class="demo-flag">Maquette</span> Données fictives pour la démonstration.</p>' : '') +
        (C.oldSiteUrl ? '<p class="hint" style="margin-top:18px"><a href="' + U.esc(C.oldSiteUrl) + '">Ancienne version du site</a></p>' : '');
    });
  }

  function sideMe() {
    var me = S.me, el = document.querySelector('[data-side-me]');
    if (!me) { el.innerHTML = '<a class="btn btn-ghost" style="width:100%" href="' + U.esc(C.loginUrl || '#accueil') + '">Se connecter</a>'; return; }
    el.innerHTML = '<a href="#profil" aria-label="Mon profil">' + U.avatar(me, 'sm') + '</a><div><b>' + U.esc(me.name) + '</b><small>@' + U.esc(me.handle) + '</small></div>';
  }

  // ------------------------------------------------------------- compteurs
  function updateCounts() {
    var c = api.unreadCounts(); S.counts = c;
    document.querySelectorAll('[data-badge]').forEach(function (b) {
      var n = c[b.getAttribute('data-badge')] || 0; b.hidden = !n; b.textContent = n > 9 ? '9+' : n;
    });
  }

  // ------------------------------------------------- actions communes (délégation)
  function openProfile(uid) {
    if (S.me && uid === S.me.id) { location.hash = 'profil'; return; }
    S.profileId = uid;
    if (location.hash === '#compte') V.compte.show(); else location.hash = 'compte';
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-action]'); if (!t) return;
    var a = t.getAttribute('data-action');
    if (a === 'profile') { U.closeSheet(); openProfile(t.getAttribute('data-user')); }
    else if (a === 'tag') { U.closeSheet(); V.explorer.search(t.getAttribute('data-tag'), 'videos'); }
    else if (a === 'match') { e.stopPropagation(); V.matchs.open(t.getAttribute('data-match')); }
    else if (a === 'open-video') { var vid = t.getAttribute('data-video'); V.reels.focusVideo(vid, (Frikip.cache || {})[vid]); location.hash = 'reels'; }
    else if (a === 'follow') {
      var uid = t.getAttribute('data-user');
      api.toggleFollow(uid).then(function (r) {
        document.querySelectorAll('[data-action="follow"][data-user="' + uid + '"]').forEach(function (b) { b.setAttribute('aria-pressed', r.following); b.textContent = r.following ? 'Abonné' : 'Suivre'; });
        V.compte.followers(uid, r.followers);
        U.toast(r.following ? 'Abonné' : 'Désabonné');
      });
    }
  });

  // --------------------------------------------------------------- démarrage
  function start() {
    shell();
    api.me().then(function (me) {
      S.me = me; sideMe();
      window.addEventListener('hashchange', route);
      route(); rail();
    });
    Frikip.bus.on('counts:changed', function () { if (C.mode === 'api' && S.me) api.me().then(function () { updateCounts(); }); else updateCounts(); });
    Frikip.bus.on('auth:required', function () { U.toast('Connecte-toi pour continuer'); if (C.loginUrl) setTimeout(function () { location.href = C.loginUrl; }, 900); });
    if (C.mode === 'api') {
      // Scores et compteurs rafraîchis depuis le serveur
      setInterval(function () { rail(); if (S.view === 'matchs' && mounted.matchs) V.matchs.show(); }, C.pollScoresMs);
      setInterval(function () { if (S.me) api.me().then(function (m) { if (m) updateCounts(); }); }, 30000);
    }
    // Scores en direct : la démo fait avancer les minutes (en production : rafraîchissement depuis le serveur)
    setInterval(function () {
      var ids = api._tick(); if (!ids.length) return;
      if (mounted.accueil) V.accueil.tick(ids);
      if (mounted.reels) V.reels.tick(ids);
      if (mounted.matchs) V.matchs.tick(ids);
      ids.forEach(function (id) { api.match(id).then(function (m) { var el = document.querySelector('[data-rail-min="' + id + '"]'); if (el) el.textContent = m.status === 'live' ? m.minute + '’ direct' : 'Terminé'; }); });
    }, 6000);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
