/*
 * MENU — gestion du compte et accès à toutes les fonctions du site :
 * compte, favoris, lives, défis, coaching, détections, offres, outils recruteur,
 * paiements, Mode Pro, paramètres, sécurité, aide, déconnexion.
 * Les éléments « url » ouvrent les pages du site Laravel (même design).
 */
window.Frikip = window.Frikip || {};
Frikip.views = Frikip.views || {};
(function () {
  var U = Frikip.ui, S = Frikip.state, C = Frikip.config, api = Frikip.api;
  var root;

  // [icône, libellé, destination, badge, rôles]   destination : #écran, url:clé de config.links, action:nom
  function groups(role) {
    var isRec = role === 'recruiter', isFan = role === 'fan', isAth = role === 'athlete';
    return [
      ['Mon compte', [
        ['user', 'Modifier mon profil', 'url:profileEdit'],
        ['film', 'Mes publications', '#profil', null, isAth],
        ['bookmark', 'Vidéos enregistrées', 'action:saved'],
        ['stats', 'Mes statistiques', 'url:stats', null, isAth],
        ['check', 'Vérifier mon compte', 'url:verification', null, !isFan],
        ['gift', 'Inviter mes amis', 'url:referral']
      ]],
      ['Activité', [
        ['bell', 'Notifications', '#notifications', 'notifications'],
        ['msg', 'Messages', '#messages', 'messages'],
        ['msg', 'Actu et stories', 'url:posts'],
        ['film', 'Lives et directs', 'action:lives'],
        ['trophy', 'Défis', 'url:challenges'],
        ['dumbbell', 'Coaching', 'url:coaching'],
        ['dumbbell', 'Mon coaching', 'url:coachingFollow', null, !isFan],
        ['calendar', 'Détections et essais', 'url:events'],
        ['flag', isRec ? 'Mes offres' : 'Offres', 'url:offers', null, !isFan],
        ['star', 'Meilleurs joueurs', 'url:rankings'],
        ['shield', 'Clubs et académies', 'url:clubs'],
        ['users', 'Acteurs du foot', 'url:actors'],
        ['party', 'Ils ont été recrutés', 'url:stories']
      ]],
      ['Recrutement', isRec ? [
        ['search', 'Recherche de joueurs', 'url:search'],
        ['star', 'Mes listes de joueurs', 'url:shortlists'],
        ['bell', 'Mes alertes', 'url:alerts'],
        ['users', 'Historique de mes contacts', 'url:contacts'],
        ['shield', 'Ma page club', 'url:clubEdit'],
        ['ball', 'Diffuser un match', 'url:liveBroadcast']
      ] : []],
      ['Services', [
        ['star', 'FootPro Premium', 'url:premium'],
        ['upload', 'Booster mon profil', 'url:boost', null, isAth],
        ['close', 'FootPro sans pub', 'url:noAds'],
        ['film', 'Montage vidéo', 'url:videoServices'],
        ['receipt', 'Mes paiements', 'url:payments'],
        ['shield', 'Offre Structure Pro', 'url:structure', null, isRec]
      ]],
      ['Paramètres et aide', [
        ['settings', 'Paramètres', 'url:settings'],
        ['close', 'Comptes bloqués', 'url:blocks'],
        ['flag', 'Aide et support', 'url:support'],
        ['users', 'Devenir partenaire', 'url:partners']
      ]]
    ];
  }

  function render() {
    var me = S.me;
    var html = '<header class="topbar"><button class="iconbtn" type="button" data-back aria-label="Retour">' + U.icon('back') + '</button><h1>Menu</h1></header>' +
      '<div class="page"><h1 class="page-title">Menu</h1>' +
      '<a class="me-card" href="#profil">' + U.avatar(me, 'md') + '<span><b>' + U.name(me) + '</b><small>@' + U.esc(me.handle) + ' · ' + U.esc(me.type || '') + '</small><em>Voir mon profil ›</em></span></a>';
    groups(me.role).forEach(function (g) {
      var items = g[1].filter(function (i) { return i[4] !== false; });
      if (!items.length) return;
      html += '<h2 class="menu-group">' + g[0] + '</h2><div class="menu-list">' + items.map(function (i) {
        return '<button class="menu-item" type="button" data-go="' + U.esc(i[2]) + '" data-label="' + U.esc(i[1]) + '">' + U.icon(i[0]) + '<span>' + i[1] + '</span>' +
          (i[3] ? '<span class="dot-badge num" data-badge="' + i[3] + '" hidden></span>' : '') + '<small aria-hidden="true">›</small></button>';
      }).join('') + '</div>';
    });
    html += (me.premium ? '' : '<div class="pro-card"><span class="crown" aria-hidden="true">' + U.icon('star') + '</span><div><b>Mode Pro</b><p>Statistiques détaillées, mise en avant auprès des recruteurs, outils de scouting.</p></div>' +
        '<button class="btn btn-green btn-sm" type="button" data-go="url:premium" data-label="Mode Pro">Passer en Pro</button></div>') +
      '<button class="logout" type="button" data-logout>' + U.icon('logout') + 'Se déconnecter</button>' +
      '<button class="logout subtle" type="button" data-go="url:deletion" data-label="Supprimer mon compte">' + U.icon('close') + 'Supprimer mon compte</button></div>';
    root.innerHTML = html;
    Frikip.bus.emit('counts:changed');
  }

  function showSaved() {
    api.saved().then(function (list) {
      Frikip.cache = Frikip.cache || {};
      list.forEach(function (v) { Frikip.cache[v.id] = v; });
      U.sheet('Vidéos enregistrées', list.length ? '<div class="grid3">' + list.map(function (v) {
        return '<button class="thumb" type="button" data-action="open-video" data-video="' + v.id + '">' + U.thumbMedia(v) + '<span class="tv">' + U.icon('play') + '<span class="num">' + U.n(v.views) + '</span></span></button>';
      }).join('') + '</div>' : '<p class="no-result">Touche le marque-page sous une vidéo pour la retrouver ici.</p>', function (sh) {
        list.forEach(function (v) { sh.querySelectorAll('[data-thumb="' + v.id + '"]').forEach(function (c) { Frikip.scene.still(c, v); }); });
        sh.addEventListener('click', function (e) { if (e.target.closest('[data-action="open-video"]')) U.closeSheet(); });
      });
    });
  }

  Frikip.views.menu = {
    mount: function (el) {
      root = el;
      el.addEventListener('click', function (e) {
        if (e.target.closest('[data-back]')) { history.length > 1 ? history.back() : (location.hash = 'profil'); return; }
        if (e.target.closest('[data-logout]')) {
          if (C.mode !== 'api') { U.toast('Déconnexion désactivée dans la maquette'); return; }
          var f = document.createElement('form'); f.method = 'POST'; f.action = (C.links || {}).logout || '/deconnexion';
          f.innerHTML = '<input type="hidden" name="_token" value="' + U.esc((document.querySelector('meta[name="csrf-token"]') || {}).content || '') + '">';
          document.body.appendChild(f); f.submit(); return;
        }
        var g = e.target.closest('[data-go]'); if (!g) return;
        var to = g.getAttribute('data-go');
        if (to === 'action:saved') { showSaved(); return; }
        if (to === 'action:lives') { Frikip.views.matchs.showLives(); location.hash = 'matchs'; return; }
        if (to.charAt(0) === '#') { location.hash = to.slice(1); return; }
        var url = to.indexOf('url:') === 0 ? (C.links || {})[to.slice(4)] : null;
        if (url && C.mode === 'api') location.href = url;
        else U.toast('« ' + g.getAttribute('data-label') + ' » s’ouvre sur le site');
      });
    },
    show: function () { render(); }
  };
})();
