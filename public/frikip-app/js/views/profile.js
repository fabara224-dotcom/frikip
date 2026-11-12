/*
 * PROFIL — le mien (#profil) ou celui d'un autre compte (#compte).
 * En-tête, Abonnés · Abonnements · Publications, Suivre / Message, onglets Publications · À propos.
 */
window.Frikip = window.Frikip || {};
Frikip.views = Frikip.views || {};
(function () {
  var U = Frikip.ui, api = Frikip.api, S = Frikip.state;
  // Pages du site Laravel ouvertes depuis le menu du profil (mode api)
  var LINKS = { edit: '/profil/modifier', 'Mon compte': '/profil/modifier', pro: '/paiement/premium', 'Paramètres': '/profil/modifier',
    'Aide et sécurité': '/support', 'Mes clubs et joueurs suivis': '/sportifs', 'Se déconnecter': '/deconnexion', 'Mes favoris': null };

  function make(isMine) {
    var root, user, vids = [], tab = 'videos';

    function thumbs(list) {
      if (!list.length) return '<p class="no-result">' + (isMine ? 'Tu n’as encore rien publié. Touche le bouton + pour poster ta première vidéo.' : 'Aucune vidéo pour le moment.') + '</p>';
      return '<div class="grid3" style="margin-top:12px">' + list.map(function (v) {
        return '<button class="thumb" type="button" data-action="open-video" data-video="' + v.id + '" aria-label="Vidéo : ' + U.esc(v.caption.slice(0, 60)) + '">' +
          U.thumbMedia(v) +
          '<span class="tv">' + U.icon('play') + '<span class="num">' + U.n(v.views) + '</span></span></button>';
      }).join('') + '</div>';
    }
    function about(u) {
      var kv = [['Type de profil', u.type], ['Ville', u.city]];
      if (u.club) kv.push(['Club', u.club.name]);
      var html = '<div class="kv">' + kv.map(function (p) { return '<div><span>' + p[0] + '</span><b>' + U.esc(p[1]) + '</b></div>'; }).join('') + '</div>';
      if (u.player) {
        var p = u.player;
        html += '<div class="section-h"><h2>Fiche joueur</h2></div><div class="kv">' +
          [['Poste', p.position], ['Pied fort', p.foot], ['Âge', p.age + ' ans'], ['Taille', p.height]].map(function (x) { return '<div><span>' + x[0] + '</span><b>' + U.esc(x[1]) + '</b></div>'; }).join('') + '</div>' +
          '<div class="section-h"><h2>Saison en cours</h2></div><div class="season">' +
          [['Buts', p.goals], ['Passes D.', p.assists], ['Matchs', p.matches], ['Minutes', p.minutes]].map(function (x) { return '<div><b class="num">' + (x[1] == null ? '–' : x[1]) + '</b><span>' + x[0] + '</span></div>'; }).join('') + '</div>';
      }
      return html;
    }
    function menu() {
      var items = [['user', 'Mon compte', 'Nom, téléphone, mot de passe'], ['bookmark', 'Mes favoris', ''], ['bell', 'Notifications', ''], ['users', 'Mes clubs et joueurs suivis', S.me ? S.me.following + ' comptes' : ''],
        ['settings', 'Paramètres', 'Langue, économie de données'], ['flag', 'Aide et sécurité', ''], ['logout', 'Se déconnecter', '']];
      return '<div class="pro-card">' + U.icon('star') + '<div><b>Mode Pro</b><p>Statistiques détaillées, mise en avant auprès des recruteurs et outils de scouting.</p></div><button class="btn btn-sm btn-green" type="button" data-menu="pro">Découvrir</button></div>' +
        '<div class="menu-list">' + items.map(function (i) {
          return '<button class="menu-item" type="button" data-menu="' + i[1] + '">' + U.icon(i[0]) + '<span>' + i[1] + '</span><small>' + i[2] + '</small></button>';
        }).join('') + '</div>';
    }

    function stats(u) {
      var views = vids.reduce(function (t, v) { return t + (v.views || 0); }, 0), likes = vids.reduce(function (t, v) { return t + (v.likes || 0); }, 0);
      var html = '<div class="season" style="grid-template-columns:repeat(3,minmax(0,1fr))">' +
        [['Vues', views], ['J’aime', likes], ['Vidéos', vids.length]].map(function (x) { return '<div><b class="num">' + U.n(x[1]) + '</b><span>' + x[0] + '</span></div>'; }).join('') + '</div>';
      if (u.player) {
        var p = u.player;
        html += '<div class="section-h"><h2>Saison en cours</h2></div><div class="season">' +
          [['Buts', p.goals], ['Passes D.', p.assists], ['Matchs', p.matches], ['Minutes', p.minutes]].map(function (x) { return '<div><b class="num">' + (x[1] == null ? '–' : x[1]) + '</b><span>' + x[0] + '</span></div>'; }).join('') + '</div>';
      }
      return html;
    }
    function render() {
      var u = user, isP = !!u.player, hasPage = u.role === 'athlete' || !!u.player || vids.length > 0;
      if (!hasPage && tab === 'videos') tab = isMine ? 'saved' : 'about';
      var coverMedia = u.cover ? '<img src="' + U.esc(u.cover) + '" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">'
        : vids[0] && (vids[0].poster || vids[0].url || vids[0].localUrl) ? U.thumbMedia(vids[0]) : '<canvas data-cover aria-hidden="true"></canvas>';
      var place = [u.type + (isP ? ' · ' + u.player.position : ''), [u.city, u.country].filter(Boolean).join(', ')].filter(Boolean).join(' • ');
      root.innerHTML =
        '<div class="pcover">' + coverMedia + '<span class="pcover-shade"></span>' +
          '<div class="pcover-bar">' + (isMine ? '<span></span>' : '<button class="iconbtn glass" type="button" data-back aria-label="Retour">' + U.icon('back') + '</button>') +
            '<span class="pcover-actions">' + (isMine ? '<label class="iconbtn glass" aria-label="Changer la photo de couverture">' + U.icon('upload') + '<input type="file" accept="image/*" data-upload="cover" hidden></label><a class="iconbtn glass" href="#menu" aria-label="Menu et paramètres">' + U.icon('settings') + '</a>'
              : '<button class="iconbtn glass" type="button" data-menu="share" aria-label="Partager le profil">' + U.icon('share') + '</button><button class="iconbtn glass" type="button" data-menu="more" aria-label="Signaler ou bloquer">' + U.icon('flag') + '</button>') + '</span></div></div>' +
        '<div class="page pbody">' +
          '<div class="pid"><div class="pid-av">' + U.avatar(u, 'xl') + (isMine ? '<label class="pid-cam" aria-label="Changer ma photo">' + U.icon('upload') + '<input type="file" accept="image/*" data-upload="photo" hidden></label>' : '') + '</div>' +
            '<div class="pid-btns">' + (isMine ? '<button class="btn btn-ghost btn-sm" type="button" data-menu="edit">Modifier le profil</button>' : U.followBtn(u) + '<button class="btn btn-ghost btn-icon" type="button" data-message="' + u.id + '" aria-label="Envoyer un message">' + U.icon('msg') + '</button>') + '</div></div>' +
          '<h2 class="pname">' + U.esc(u.name) + (u.verified ? U.VERIF : '') + '</h2>' +
          '<div class="pplace">' + U.esc(place) + '</div>' +
          (hasPage ? '<div class="pstats"><div><b class="num" data-followers>' + U.n(u.followers) + '</b><span>Abonnés</span></div><div><b class="num">' + U.n(u.following) + '</b><span>Abonnements</span></div><div><b class="num">' + vids.length + '</b><span>Publications</span></div></div>'
            : '<div class="pstats"><div><b class="num">' + U.n(u.following) + '</b><span>Abonnements</span></div></div>') +
          (u.managedBy ? '<p class="hint" style="margin:4px 0 0">Profil tenu par : ' + U.esc(u.managedBy) + '</p>' : '') +
          (u.bio ? '<p class="pbio">' + U.caption(u.bio) + '</p>' : '') +
          '<div class="p-tabs" role="group" aria-label="Contenu du profil">' +
            (hasPage ? '<button type="button" data-ptab="videos" aria-pressed="' + (tab === 'videos') + '">Publications</button>' : '') +
            (isMine ? '<button type="button" data-ptab="saved" aria-pressed="' + (tab === 'saved') + '">Enregistrées</button>' : '') +
            (hasPage ? '<button type="button" data-ptab="stats" aria-pressed="' + (tab === 'stats') + '">Stats</button>' : '') +
            '<button type="button" data-ptab="about" aria-pressed="' + (tab === 'about') + '">À propos</button></div>' +
          '<div data-pbody>' + body() + '</div>' +
        '</div>';
      var cover = root.querySelector('[data-cover]');
      if (cover) Frikip.scene.still(cover, { id: u.id, scene: vids[0] ? vids[0].scene : 'crowd', hue: u.color }, 2.4);
      paint();
    }
    var saved = null;
    function body() {
      if (tab === 'saved') {
        if (!saved) { api.saved().then(function (l) { saved = l; Frikip.cache = Frikip.cache || {}; l.forEach(function (v) { Frikip.cache[v.id] = v; }); if (tab === 'saved') { root.querySelector('[data-pbody]').innerHTML = body(); paint(); } }); return '<p class="hint" style="margin-top:14px">Chargement…</p>'; }
        return saved.length ? thumbs(saved) : '<p class="no-result">Touche le marque-page sous une vidéo pour la retrouver ici.</p>';
      }
      return tab === 'videos' ? thumbs(vids) : tab === 'stats' ? stats(user) : about(user);
    }
    function paint() { vids.concat(saved || []).forEach(function (v) { root.querySelectorAll('[data-thumb="' + v.id + '"]').forEach(function (c) { Frikip.scene.still(c, v); }); }); }

    function load() {
      var id = isMine ? 'me' : S.profileId;
      if (!id) return;
      if (isMine && !S.me) return;
      Promise.all([isMine ? api.me() : api.user(id), api.userVideos(isMine ? S.me.id : id)]).then(function (r) { user = r[0]; vids = r[1]; tab = 'videos'; saved = null; render(); root.scrollTop = 0; });
    }

    return {
      mount: function (el) {
        root = el;
        el.addEventListener('change', function (e) {
          var inp = e.target.closest('[data-upload]'); if (!inp || !inp.files[0]) return;
          var f = inp.files[0], kind = inp.getAttribute('data-upload');
          if (!/^image\//.test(f.type) || f.size > 8 * 1024 * 1024) { U.toast('Choisis une photo (8 Mo maximum).'); return; }
          U.toast('Envoi de la photo…');
          api.uploadPhoto(kind, f).then(function (r) {
            user[kind] = r.url; if (S.me) S.me[kind] = r.url;
            render(); U.toast(kind === 'photo' ? 'Photo de profil mise à jour' : 'Couverture mise à jour');
            Frikip.bus.emit('me:changed');
          }).catch(function (x) { U.toast(x.message || 'Envoi impossible'); });
        });
        el.addEventListener('click', function (e) {
          var t = e.target.closest('[data-ptab]');
          if (t) { tab = t.getAttribute('data-ptab'); el.querySelectorAll('[data-ptab]').forEach(function (b) { b.setAttribute('aria-pressed', b === t); }); el.querySelector('[data-pbody]').innerHTML = body(); paint(); return; }
          if (e.target.closest('[data-back]')) { history.length > 1 ? history.back() : (location.hash = 'accueil'); return; }
          var m = e.target.closest('[data-message]'); if (m) { Frikip.views.messages.openWith(m.getAttribute('data-message')); return; }
          var mn = e.target.closest('[data-menu]');
          if (mn) {
            var k = mn.getAttribute('data-menu');
            if (k === 'Notifications') location.hash = 'notifications';
            else if (k === 'more') {
              U.sheet(user.name, '<div class="menu-list" style="margin:0;border:0">' +
                '<button class="menu-item" type="button" data-pm="report">' + U.icon('flag') + '<span>Signaler ce profil</span></button>' +
                '<button class="menu-item danger" type="button" data-pm="block">' + U.icon('close') + '<span>Bloquer ' + U.esc(user.name) + '</span></button></div>', function (sh) {
                sh.addEventListener('click', function (x) {
                  var o = x.target.closest('[data-pm]'); if (!o) return; U.closeSheet();
                  if (!S.me) { Frikip.bus.emit('auth:required'); return; }
                  if (o.getAttribute('data-pm') === 'block') api.block(user.accountId || user.id).then(function (r) { U.toast(r.message || 'Bloqué'); history.back(); }).catch(function (y) { U.toast(y.message); });
                  else api.report('profile', user.id, 'Profil signalé depuis l’application').then(function () { U.toast('Merci, le profil est envoyé à la modération.'); }).catch(function (y) { U.toast(y.message); });
                });
              });
            }
            else if (k === 'share') { var link = (user.profileUrl || ('https://myfootpro.com/@' + user.handle)); if (navigator.clipboard) navigator.clipboard.writeText(link).catch(function () {}); U.toast('Lien du profil copié : ' + link); }
            else if (k === 'edit' && Frikip.config.mode === 'api') location.href = (Frikip.config.links || {}).profileEdit || LINKS.edit;
            else if (Frikip.config.mode === 'api' && LINKS[k]) {
              if (k === 'Se déconnecter') {
                var f = document.createElement('form'); f.method = 'POST'; f.action = LINKS[k];
                f.innerHTML = '<input type="hidden" name="_token" value="' + U.esc((document.querySelector('meta[name="csrf-token"]') || {}).content || '') + '">';
                document.body.appendChild(f); f.submit();
              } else location.href = LINKS[k];
            }
            else if (k === 'Se déconnecter') U.toast('Déconnexion désactivée dans la maquette');
            else U.toast('« ' + (k === 'edit' ? 'Modifier le profil' : k === 'pro' ? 'Mode Pro' : k) + ' » arrive à l’étape suivante');
          }
        });
      },
      show: function () { load(); },
      followers: function (uid, n) { if (user && user.id === uid) { user.followers = n; var f = root.querySelector('[data-followers]'); if (f) f.textContent = U.n(n); } }
    };
  }

  Frikip.views.profil = make(true);
  Frikip.views.compte = make(false);
})();
