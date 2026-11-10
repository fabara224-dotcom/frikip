/*
 * MESSAGES — liste des conversations + discussion.
 * Téléphone : la liste, puis la discussion en plein écran (bouton retour). Ordinateur : les deux côte à côte.
 */
window.Frikip = window.Frikip || {};
Frikip.views = Frikip.views || {};
(function () {
  var U = Frikip.ui, api = Frikip.api, S = Frikip.state;
  var root, layout, convs = [];

  function convRow(c) {
    var mine = c.last && c.last.from !== c.withId;
    return '<button class="conv' + (c.unread ? ' unread' : '') + '" type="button" data-conv="' + c.id + '" aria-current="' + (S.conversationId === c.id) + '">' + U.avatar(c.with, 'md') +
      '<span class="who"><b>' + U.name(c.with) + '</b><small>' + (c.last ? (mine ? 'Toi : ' : '') + U.esc(c.last.text) : 'Nouvelle conversation') + '</small></span>' +
      '<span class="side-meta"><span>' + (c.last ? U.ago(c.last.at).replace('il y a ', '') : '') + '</span>' + (c.unread ? '<span class="cnt num">' + c.unread + '</span>' : '') + '</span></button>';
  }

  function render() {
    root.innerHTML = '<div class="msg-layout"><div class="convs-wrap">' +
      '<header class="topbar"><h1>Messages</h1></header>' +
      '<div class="page" style="padding-bottom:0;width:100%"><h1 class="page-title">Messages</h1></div>' +
      '<div class="convs" data-convs></div></div>' +
      '<section class="thread" data-thread aria-label="Discussion"><div style="margin:auto;color:var(--muted);text-align:center;padding:24px">Choisis une conversation pour l’ouvrir.</div></section></div>';
    layout = root.querySelector('.msg-layout');
    listConvs();
  }

  function listConvs() {
    api.conversations().then(function (cs) {
      convs = cs;
      root.querySelector('[data-convs]').innerHTML = cs.map(convRow).join('');
      if (!S.conversationId && window.matchMedia('(min-width: 1024px)').matches && cs[0]) open(cs[0].id);
    });
  }

  function bubbles(c) {
    var out = '', lastDay = '';
    c.messages.forEach(function (m) {
      var d = new Date(m.at), day = d.toDateString();
      if (day !== lastDay) {
        lastDay = day;
        var today = new Date().toDateString() === day, yest = new Date(Date.now() - 864e5).toDateString() === day;
        out += '<div class="day-sep">' + (today ? 'Aujourd’hui' : yest ? 'Hier' : d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })) + '</div>';
      }
      out += '<div class="bubble' + (m.from !== c.withId ? ' me' : '') + '">' + U.esc(m.text) + '<time class="num">' + U.hhmm(m.at) + '</time></div>';
    });
    return out;
  }

  function open(id) {
    S.conversationId = id;
    api.conversation(id).then(function (c) {
      lastCount = c.messages.length;
      var th = root.querySelector('[data-thread]');
      th.innerHTML =
        '<div class="thread-head"><button class="iconbtn back" type="button" data-back aria-label="Retour aux conversations">' + U.icon('back') + '</button>' +
          '<button type="button" data-action="profile" data-user="' + c.with.id + '" aria-label="Profil de ' + U.esc(c.with.name) + '">' + U.avatar(c.with, 'sm') + '</button>' +
          '<button class="who" type="button" data-action="profile" data-user="' + c.with.id + '"><b>' + U.name(c.with) + '</b><small>' + U.esc(c.with.type || '') + '</small></button>' +
          '<button class="iconbtn" type="button" data-call="audio" aria-label="Appel audio">' + U.icon('phone') + '</button>' +
          '<button class="iconbtn" type="button" data-call="video" aria-label="Appel vidéo">' + U.icon('camera') + '</button>' +
          (c.pageUrl ? '<a class="iconbtn" href="' + U.esc(c.pageUrl) + '" aria-label="Vocal, photo et options">' + U.icon('upload') + '</a>' : '') + '</div>' +
        '<div class="bubbles" data-bubbles aria-live="polite">' + (c.messages.length ? bubbles(c) : '<div class="day-sep">Dis bonjour à ' + U.esc(c.with.name) + '</div>') + '</div>' +
        '<form class="composer" data-send><label class="sr-only" for="msg-input">Message</label><input id="msg-input" placeholder="Écris un message…" autocomplete="off" maxlength="1000">' +
          '<button class="send" type="submit" aria-label="Envoyer">' + U.icon('send') + '</button></form>';
      layout.classList.add('open');
      var b = th.querySelector('[data-bubbles]'); b.scrollTop = b.scrollHeight;
      var row = root.querySelector('[data-conv="' + id + '"]');
      root.querySelectorAll('.conv').forEach(function (r) { r.setAttribute('aria-current', r === row); });
      if (row) { row.classList.remove('unread'); var cnt = row.querySelector('.cnt'); if (cnt) cnt.remove(); }
      Frikip.bus.emit('counts:changed');
      if (window.matchMedia('(min-width: 1024px)').matches || document.activeElement === document.body) th.querySelector('input').focus({ preventScroll: true });
    });
  }

  var lastCount = 0;
  if (Frikip.config.mode === 'api') {
    // Nouveaux messages : la discussion ouverte est relue régulièrement
    setInterval(function () {
      if (S.view !== 'messages' || !S.conversationId || !root) return;
      api.conversation(S.conversationId).then(function (c) {
        if (c.messages.length === lastCount) return;
        lastCount = c.messages.length;
        var b = root.querySelector('[data-bubbles]'); if (!b) return;
        b.innerHTML = bubbles(c); b.scrollTop = b.scrollHeight;
      }).catch(function () {});
    }, Frikip.config.pollMessagesMs);
  }

  function send(text) {
    var id = S.conversationId;
    api.sendMessage(id, text).catch(function (e) { U.toast(e.message); throw e; }).then(function (m) {
      lastCount++;
      var b = root.querySelector('[data-bubbles]');
      b.insertAdjacentHTML('beforeend', '<div class="bubble me">' + U.esc(m.text) + '<time class="num">' + U.hhmm(m.at) + '</time></div>');
      b.scrollTop = b.scrollHeight;
      refreshRow(id);
      if (Frikip.config.mode === 'demo') {   // démo : l'autre personne répond
        setTimeout(function () { if (S.conversationId === id) b.insertAdjacentHTML('beforeend', '<div class="typing" data-typing>écrit…</div>'), b.scrollTop = b.scrollHeight; }, 700);
        setTimeout(function () {
          var r = api._fakeReply(id); var ty = b.querySelector('[data-typing]'); if (ty) ty.remove();
          if (S.conversationId === id && r) { b.insertAdjacentHTML('beforeend', '<div class="bubble">' + U.esc(r.text) + '<time class="num">' + U.hhmm(r.at) + '</time></div>'); b.scrollTop = b.scrollHeight; }
          refreshRow(id);
        }, 2100);
      }
    });
  }
  function refreshRow(id) {
    api.conversations().then(function (cs) {
      convs = cs;
      var wrap = root.querySelector('[data-convs]'); wrap.innerHTML = cs.map(function (c) { if (c.id === id) c.unread = 0; return convRow(c); }).join('');
    });
  }

  Frikip.views.messages = {
    mount: function (el) {
      root = el; render();
      el.addEventListener('click', function (e) {
        var c = e.target.closest('[data-conv]'); if (c) { open(c.getAttribute('data-conv')); return; }
        if (e.target.closest('[data-back]')) { layout.classList.remove('open'); S.conversationId = null; return; }
        var cl = e.target.closest('[data-call]');
        if (cl && S.conversationId) {
          api.call(S.conversationId, cl.getAttribute('data-call')).then(function (r) { if (r.url) location.href = r.url; }).catch(function (x) { U.toast(x.message); });
        }
      });
      el.addEventListener('submit', function (e) {
        if (!e.target.matches('[data-send]')) return;
        e.preventDefault(); var inp = e.target.querySelector('input'), t = inp.value.trim(); if (!t) return; inp.value = ''; send(t);
      });
    },
    show: function () { if (root) listConvs(); },
    openWith: function (userId) {
      api.openConversationWith(userId).then(function (c) {
        location.hash = 'messages';
        setTimeout(function () { listConvs(); open(c.id); }, 30);
      });
    }
  };
})();
