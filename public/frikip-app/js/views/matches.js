/*
 * MATCHS — en direct, à venir, résultats ; détail d'un match (actions et statistiques).
 * En production : scores des grands championnats via API-Football (côté serveur Laravel),
 * matchs locaux saisis par les clubs.
 */
window.Frikip = window.Frikip || {};
Frikip.views = Frikip.views || {};
(function () {
  var U = Frikip.ui, api = Frikip.api;
  var root, tab = 'tout';
  var TABS = [['tout', 'Tout'], ['lives', 'Lives'], ['direct', 'En direct'], ['aujourdhui', 'Aujourd’hui'], ['demain', 'Demain'], ['resultats', 'Résultats']];
  var REACT = ['⚽', '🔥', '👏', '😮', '😂', '🟨', '🟥', '🧤'];
  var chatTimer = null;
  var L = function () { return Frikip.config.links || {}; };

  // ----------------------------------------------------------------- lives
  function streamCard(s) {
    var owner = s.owner, live = s.status === 'direct';
    var pic = owner ? U.avatar(owner, 'md') : '<span class="av av-md" style="background:var(--surface-2)" aria-hidden="true">' + U.icon('ball') + '</span>';
    var sub = s.kind === 'membre' ? (owner ? U.esc(owner.name) + ' · ' + U.esc(owner.type || '') : 'Live d’un membre')
      : (s.hasScore ? '<b class="num">' + U.esc(s.home) + ' ' + s.hs + '–' + s.as + ' ' + U.esc(s.away) + '</b>' + (s.minute ? ' · ' + U.esc(s.minute) + '’' : '') : U.esc(s.competition || 'Match diffusé'));
    var when = !live && s.startsAt ? new Date(s.startsAt).toLocaleString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '';
    return '<a class="live-row-card' + (live ? ' on' : '') + '" href="' + U.esc(s.url) + '">' +
      '<span class="lrc-av">' + pic + (live ? '<span class="lrc-badge">LIVE</span>' : '') + '</span>' +
      '<span class="lrc-tx"><b>' + U.esc(s.title) + '</b><small>' + sub + '</small></span>' +
      '<span class="lrc-side">' + (live ? '<span class="lrc-eye">' + U.icon('users') + '<span class="num">' + U.n(s.viewers || 0) + '</span></span>' : '<small>' + U.esc(when) + '</small>') + '</span></a>';
  }
  function drawLives(list) {
    api.lives().then(function (d) {
      var me = Frikip.state.me, html = '';
      html += '<div class="live-actions">' +
        '<a class="btn btn-green" href="' + U.esc(d.links.go || L().liveGo || '#') + '" data-golive>' + U.icon('film') + 'Lancer un live</a>' +
        (d.canBroadcast ? '<a class="btn btn-ghost" href="' + U.esc(d.links.broadcast || '#') + '">' + U.icon('ball') + 'Diffuser un match</a>' : '') + '</div>' +
        (me && d.liveRefusal ? '<p class="hint" style="margin:8px 0 0">' + U.esc(d.liveRefusal) + '</p>' : '');
      if (d.partner) html += '<a class="partner-card" href="' + U.esc(d.partner.url) + '"><span class="pill-live"><i class="pulse"></i>Direct</span><b>Tous les matchs en direct</b><small>avec ' + U.esc(d.partner.name) + '</small></a>';
      html += d.members.length ? section('Lives des membres', d.members.map(streamCard).join('')) : '';
      html += d.matches.length ? section('Matchs diffusés en direct', d.matches.map(streamCard).join('')) : '';
      html += d.big.length ? section('Grands matchs du jour', '<div class="mlist">' + d.big.map(function (m) { return row(m, m.tv); }).join('') + '</div>') : '';
      html += d.soon.length ? section('Bientôt en direct', d.soon.map(streamCard).join('')) : '';
      if (!d.members.length && !d.matches.length && !d.big.length && !d.soon.length) html += '<p class="no-result">Aucun live pour le moment. Lance le premier !</p>';
      if (L().liveAll) html += '<p class="hint" style="margin-top:16px"><a href="' + U.esc(L().liveAll) + '">Voir la page complète des directs</a></p>';
      list.innerHTML = html;
    });
  }

  // ----------------------------------------------------------------- salon (chat en direct)
  function chat(sh, room) {
    var box = sh.querySelector('[data-chat]'), after = 0, seen = {}, me = Frikip.state.me;
    function add(ms) {
      ms = ms.filter(function (m) { if (seen[m.id]) return false; seen[m.id] = 1; return true; });
      ms.forEach(function (m) {
        after = Math.max(after, m.id);
        box.insertAdjacentHTML('beforeend', m.kind === 'react' ? '<div class="chat-react' + (m.mine ? ' me' : '') + '"><span>' + U.esc(m.body) + '</span><small>' + U.esc(m.name) + '</small></div>'
          : '<div class="chat-msg' + (m.mine ? ' me' : '') + '"><b>' + U.esc(m.name) + '</b> ' + U.esc(m.body) + '<time class="num">' + U.esc(m.at || '') + '</time></div>');
      });
      if (ms.length) box.scrollTop = box.scrollHeight;
      var empty = box.querySelector('[data-empty]'); if (empty && box.children.length > 1) empty.remove();
    }
    function poll() { api.roomSince(room, after).then(add).catch(function () {}); }
    poll(); clearInterval(chatTimer); chatTimer = setInterval(function () { if (!document.body.contains(box)) { clearInterval(chatTimer); return; } poll(); }, 4000);
    sh.querySelector('[data-chat-form]').addEventListener('submit', function (e) {
      e.preventDefault();
      if (!me) { Frikip.bus.emit('auth:required'); return; }
      var inp = e.target.querySelector('input'), t = inp.value.trim(); if (!t) return; inp.value = '';
      api.roomPost(room, t, 'text').then(poll).catch(function (x) { U.toast(x.message); });
    });
    sh.querySelectorAll('[data-react]').forEach(function (b) {
      b.addEventListener('click', function () {
        if (!me) { Frikip.bus.emit('auth:required'); return; }
        api.roomPost(room, b.getAttribute('data-react'), 'react').then(poll).catch(function (x) { U.toast(x.message); });
        b.classList.remove('pop'); void b.offsetWidth; b.classList.add('pop');
      });
    });
  }

  function card(m) {
    var live = m.status === 'live', done = m.status === 'finished';
    var mid = live || done
      ? '<span class="score num" data-score="' + m.id + '">' + m.hs + '–' + m.as + '</span><span class="st num' + (live ? ' on' : '') + '" data-min="' + m.id + '">' + (live ? m.minute + '’' : 'Terminé') + '</span>'
      : '<span class="when num">' + U.esc(m.kickoff) + '</span><span class="st">' + U.esc(m.day) + '</span>';
    return '<button class="match' + (live ? ' is-live' : '') + '" type="button" data-action="match" data-match="' + m.id + '" aria-label="' + U.esc(m.homeClub.name + ' contre ' + m.awayClub.name) + '">' +
      '<span class="comp">' + (live ? '<span class="pill-live"><i class="pulse"></i>Live</span>' : '') + '<span style="flex:1;text-align:left;margin-left:8px">' + U.esc(m.comp) + (m.round ? ' · ' + U.esc(m.round) : '') + '</span>' + U.icon('bell') + '</span>' +
      '<span class="team">' + U.crest(m.homeClub, 44) + '<span>' + U.esc(m.homeClub.name) + '</span></span>' +
      '<span class="mid">' + mid + '</span>' +
      '<span class="team">' + U.crest(m.awayClub, 44) + '<span>' + U.esc(m.awayClub.name) + '</span></span></button>';
  }

  function render() {
    root.innerHTML =
      '<header class="topbar"><h1>Matchs</h1><a class="iconbtn" href="#notifications" aria-label="Notifications">' + U.icon('bell') + '<span class="dot-badge" data-badge="notifications" hidden></span></a></header>' +
      '<div class="page"><h1 class="page-title">Matchs</h1><div class="chip-row" role="group" aria-label="Période">' + TABS.map(function (t) {
        return '<button class="chip" type="button" data-mtab="' + t[0] + '" aria-pressed="' + (tab === t[0]) + '">' + (t[0] === 'direct' ? '<span class="live-dot" aria-hidden="true"></span>' : '') + t[1] + '</button>';
      }).join('') + '</div><div data-list style="margin-top:16px"></div>' +
      (Frikip.config.mode === 'demo' ? '<div class="note-card">Premier League, Ligue des champions, Liga, Serie A et Bundesliga : scores en direct fournis par API-Football une fois le site branché. Cette maquette n’affiche que des matchs fictifs.</div>' : '<p class="hint" style="margin-top:14px">Grands championnats : scores fournis par API-Football, mis à jour toutes les 30 secondes.</p>') + '</div>';
    draw();
  }
  function row(m, tv) {
    var live = m.status === 'live', done = m.status === 'finished';
    var mid = live || done ? '<b class="num' + (live ? ' on' : '') + '">' + m.hs + ' – ' + m.as + '</b><small class="num' + (live ? ' on' : '') + '" data-min="' + m.id + '">' + (live ? m.minute + '’' : 'Terminé') + '</small>'
      : '<b class="num">' + U.esc(m.kickoff || '–') + '</b><small>' + U.esc(m.day || '') + '</small>';
    return '<button class="mrow" type="button" data-action="match" data-match="' + m.id + '">' +
      '<span class="mrow-comp">' + U.esc(m.comp) + (m.round ? ' · ' + U.esc(m.round) : '') + (done && m.day ? ' · ' + U.esc(m.day) : '') + (tv ? ' · <span class="tv">' + U.icon('film') + U.esc(tv) + '</span>' : '') + '</span>' +
      '<span class="mrow-t">' + U.crest(m.homeClub, 30) + '<span>' + U.esc(m.homeClub.name) + '</span></span>' +
      '<span class="mrow-mid">' + mid + '</span>' +
      '<span class="mrow-t right"><span>' + U.esc(m.awayClub.name) + '</span>' + U.crest(m.awayClub, 30) + '</span></button>';
  }
  function section(title, html, more) {
    return '<div class="section-h"><h2>' + title + '</h2>' + (more ? '<button type="button" data-mtab-go="' + more + '">Voir tout</button>' : '') + '</div>' + html;
  }
  function draw() {
    var list = root.querySelector('[data-list]');
    if (tab === 'lives') { drawLives(list); return; }
    api.matches('tout').then(function (ms) {
      var live = ms.filter(function (m) { return m.status === 'live'; });
      var next = ms.filter(function (m) { return m.status === 'upcoming'; });
      var done = ms.filter(function (m) { return m.status === 'finished'; });
      var today = function (m) { return /aujourd/i.test(m.day || '') || m.status === 'live'; };
      var html = '';
      if (tab === 'tout') {
        html = (live.length ? section('Matchs en direct', live.map(card).join('')) : '') +
          (next.length ? section('Prochains matchs', '<div class="mlist">' + next.slice(0, 6).map(row).join('') + '</div>', next.length > 6 ? 'aujourdhui' : '') : '') +
          (done.length ? section('Résultats récents', '<div class="mlist">' + done.slice(0, 6).map(row).join('') + '</div>', done.length > 6 ? 'resultats' : '') : '');
      } else if (tab === 'direct') html = live.map(card).join('');
      else if (tab === 'aujourdhui') html = '<div class="mlist">' + ms.filter(today).map(row).join('') + '</div>';
      else if (tab === 'demain') html = '<div class="mlist">' + next.filter(function (m) { return /demain/i.test(m.day || ''); }).map(row).join('') + '</div>';
      else html = '<div class="mlist">' + done.map(row).join('') + '</div>';
      list.innerHTML = /mrow|match/.test(html) ? html : '<p class="no-result">Aucun match pour le moment.</p>';
    });
  }

  function openMatch(id) {
    api.match(id).then(function (m) {
      var live = m.status === 'live', started = live || m.status === 'finished';
      var head = '<div class="match" style="border:0;background:none;padding:6px 0 14px">' +
        '<span class="team">' + U.crest(m.homeClub, 52) + '<span>' + U.esc(m.homeClub.name) + '</span></span>' +
        '<span class="mid">' + (started ? '<span class="score num">' + m.hs + '–' + m.as + '</span><span class="st num' + (live ? ' on' : '') + '">' + (live ? m.minute + '’ · Direct' : 'Terminé') + '</span>' : '<span class="when num">' + U.esc(m.kickoff) + '</span><span class="st">' + U.esc(m.day) + '</span>') + '</span>' +
        '<span class="team">' + U.crest(m.awayClub, 52) + '<span>' + U.esc(m.awayClub.name) + '</span></span></div>';
      var events = (m.events || []).slice().reverse().map(function (e) {
        var club = e.side === 'h' ? m.homeClub : m.awayClub;
        return '<li><span class="mn num">' + e.m + '’</span><span>' + (e.t === 'but' ? '<i class="ev ev-goal" aria-hidden="true"></i>But · ' : (e.t === 'rouge' ? '<i class="ev ev-card" style="background:var(--live)" aria-hidden="true"></i>Carton rouge · ' : '<i class="ev ev-card" aria-hidden="true"></i>Carton jaune · ')) + U.esc(e.who) + ' <span style="color:var(--muted)">(' + U.esc(club.short) + ')</span></span></li>';
      }).join('');
      var stats = m.stats ? Object.keys(m.stats).map(function (k) {
        var s = m.stats[k], tot = s[0] + s[1] || 1, label = { possession: 'Possession (%)', tirs: 'Tirs', cadres: 'Tirs cadrés', corners: 'Corners' }[k] || k;
        return '<div class="stat-bar"><span class="lbl">' + label + '</span><span class="num">' + s[0] + '</span><span class="bars"><i style="width:' + (s[0] / tot * 100) + '%"></i><u style="width:' + (s[1] / tot * 100) + '%"></u></span><span class="num" style="text-align:right">' + s[1] + '</span></div>';
      }).join('') : '';
      var resume = (events ? '<h4 style="margin:6px 0 10px">Actions</h4><ul class="timeline">' + events + '</ul>' : '') +
        (stats ? '<h4 style="margin:20px 0 12px">Statistiques</h4>' + stats : '');
      var room = m.room || ('m-' + m.id);
      var body = head +
        '<div class="seg-tabs" role="group" aria-label="Match"><button type="button" data-st="salon" aria-pressed="true">' + U.icon('msg') + 'Salon en direct</button><button type="button" data-st="resume" aria-pressed="false">Résumé</button></div>' +
        '<div data-pane="salon"><div class="chat" data-chat aria-live="polite"><p class="hint" data-empty>Sois le premier à réagir à ce match.</p></div>' +
          '<div class="reacts">' + REACT.map(function (r) { return '<button type="button" data-react="' + r + '" aria-label="Réagir ' + r + '">' + r + '</button>'; }).join('') + '</div>' +
          '<form class="composer" data-chat-form style="padding:10px 0 0;border:0"><label class="sr-only" for="chat-input">Message</label><input id="chat-input" maxlength="280" placeholder="Réagis au match…" autocomplete="off"><button class="send" type="submit" aria-label="Envoyer">' + U.icon('send') + '</button></form></div>' +
        '<div data-pane="resume" hidden>' + (resume || '<p class="hint">Les actions et statistiques apparaissent pendant le match.</p>') +
          (m.url ? '<p class="hint" style="margin-top:14px"><a href="' + U.esc(m.url) + '">Compositions, classement et page complète du match</a></p>' : '') + '</div>' +
        (!started ? '<p class="hint">Coup d’envoi ' + U.esc((m.day || '').toLowerCase()) + (m.kickoff ? ' à ' + U.esc(m.kickoff) : '') + (m.venue ? ' · ' + U.esc(m.venue) : '') + '</p><button class="btn btn-green" type="button" data-remind>Me prévenir au coup d’envoi</button>' : '') +
        (live ? '<p class="hint" style="margin-top:14px">' + U.esc(m.venue) + ' · le score se met à jour tout seul.</p>' : '');
      U.sheet(m.comp + (m.round ? ' · ' + m.round : ''), body, function (sh) {
        sh.querySelectorAll('[data-st]').forEach(function (t) {
          t.addEventListener('click', function () {
            sh.querySelectorAll('[data-st]').forEach(function (o) { o.setAttribute('aria-pressed', o === t); });
            sh.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== t.getAttribute('data-st'); });
          });
        });
        chat(sh, room);
        var b = sh.querySelector('[data-remind]');
        if (b) b.addEventListener('click', function () { b.textContent = 'Rappel activé ✓'; b.disabled = true; U.toast('On te prévient au coup d’envoi'); });
      });
    });
  }

  Frikip.views.matchs = {
    mount: function (el) {
      root = el; render();
      el.addEventListener('click', function (e) {
        var t = e.target.closest('[data-mtab]');
        if (t) { tab = t.getAttribute('data-mtab'); if (location.hash !== '#matchs') location.hash = 'matchs'; el.querySelectorAll('[data-mtab]').forEach(function (b) { b.setAttribute('aria-pressed', b === t); }); draw(); return; }
        var g = e.target.closest('[data-mtab-go]');
        if (g) { tab = g.getAttribute('data-mtab-go'); el.querySelectorAll('[data-mtab]').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-mtab') === tab); }); draw(); }
      });
    },
    show: function () { if (root) draw(); },
    open: openMatch,
    showLives: function () { tab = 'lives'; if (root) { root.querySelectorAll('[data-mtab]').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-mtab') === 'lives'); }); draw(); } },
    tick: function (ids) {
      if (!root) return;
      ids.forEach(function (id) {
        api.match(id).then(function (m) {
          var mn = root.querySelector('[data-min="' + id + '"]'); if (mn) mn.textContent = m.status === 'live' ? m.minute + '’' : 'Terminé';
        });
      });
    }
  };
})();
