/*
 * NOTIFICATIONS — j'aime, abonnés, commentaires, buts en direct, défis.
 * En production : table notifications Laravel + notifications push (Web Push / Firebase dans l'app).
 */
window.Frikip = window.Frikip || {};
Frikip.views = Frikip.views || {};
(function () {
  var U = Frikip.ui, api = Frikip.api;
  var KIND = {
    like: ['heart', 'var(--live)', '#fff'], follow: ['user', 'var(--green)', 'var(--green-ink)'], comment: ['comment', 'var(--fg)', 'var(--bg)'],
    live: ['ball', 'var(--live)', '#fff'], challenge: ['trophy', 'var(--gold)', '#1a1200']
  };
  var root;

  function row(n) {
    var k = KIND[n.kind] || KIND.comment;
    var who = n.actor ? '<button type="button" data-action="profile" data-user="' + n.actor.id + '" style="font-weight:800">' + U.esc(n.actor.name) + '</button> ' : '';
    var pic = n.actor ? U.avatar(n.actor, 'md') : '<span class="av av-md" style="background:var(--surface-2)" aria-hidden="true">' + U.icon(k[0]) + '</span>';
    var action = n.kind === 'follow' && n.actor ? U.followBtn(n.actor, true) : n.kind === 'live' ? '<button class="btn btn-sm btn-ghost" type="button" data-action="match" data-match="' + n.matchId + '">Voir</button>' : '';
    return '<div class="notif' + (n.read ? '' : ' unread') + '"><span style="position:relative">' + pic +
      '<span class="kind" style="background:' + k[1] + ';color:' + k[2] + '">' + U.icon(k[0]) + '</span></span>' +
      '<div class="tx">' + who + (n.url ? '<a href="' + U.esc(n.url) + '" style="text-decoration:none">' + U.esc(n.text) + '</a>' : U.esc(n.text)) + '<small>' + U.ago(n.at) + '</small></div>' + action + '</div>';
  }

  Frikip.views.notifications = {
    mount: function (el) { root = el; },
    show: function () {
      api.notifications().then(function (list) {
        var fresh = list.filter(function (n) { return !n.read; }), old = list.filter(function (n) { return n.read; });
        root.innerHTML = '<header class="topbar"><button class="iconbtn" type="button" data-back aria-label="Retour">' + U.icon('back') + '</button><h1>Notifications</h1></header>' +
          '<div class="page"><h1 class="page-title">Notifications</h1>' +
          (fresh.length ? '<div class="section-h" style="margin-top:4px"><h2>Nouveau</h2></div>' + fresh.map(row).join('') : '') +
          (old.length ? '<div class="section-h"><h2>Plus tôt</h2></div>' + old.map(row).join('') : '') +
          (!list.length ? '<p class="no-result">Aucune notification pour le moment.</p>' : '') + '</div>';
        root.querySelector('[data-back]').addEventListener('click', function () { history.length > 1 ? history.back() : (location.hash = 'accueil'); });
        setTimeout(function () { api.markNotificationsRead().then(function () { Frikip.bus.emit('counts:changed'); }); }, 1200);
      });
    }
  };
})();
