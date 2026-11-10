/*
 * COUCHE DE DONNÉES — le seul endroit qui sait d'où viennent les données.
 *
 * Chaque écran appelle Frikip.api.xxx() et reçoit une Promise.
 *  - mode "demo" : réponses calculées dans le navigateur à partir de demo-data.js
 *  - mode "api"  : appels HTTP vers le site Laravel (routes listées dans README.md)
 *
 * Pour brancher la vraie base : écrire les routes Laravel qui renvoient le même format,
 * puis passer Frikip.config.mode à 'api'. Les écrans ne changent pas.
 */
window.Frikip = window.Frikip || {};
(function () {
  // ------------------------------------------------------------ mode API (Laravel)
  function http(method, path, body, isForm) {
    var opts = { method: method, credentials: 'include', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
    var csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) opts.headers['X-CSRF-TOKEN'] = csrf.content;
    if (body && isForm) opts.body = body;
    else if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    return fetch(Frikip.config.apiBase + path, opts).then(function (r) {
      if (r.status === 401) { Frikip.bus.emit('auth:required'); throw new Error('Connecte-toi pour continuer.'); }
      return r.json().then(function (d) { if (!r.ok) throw new Error(d.message || 'Erreur ' + r.status); return d; });
    });
  }

  var remote = {
    me: function () {
      return http('GET', '/me').then(function (u) {
        if (u.guest) { Frikip.state.counts = { messages: 0, notifications: 0 }; return null; }
        Frikip.state.counts = { messages: u.unread_messages || 0, notifications: u.unread_notifications || 0 };
        return u;
      });
    },
    feed: function (tab, page, focus) { return http('GET', '/feed?tab=' + tab + '&page=' + (page || 1) + (focus ? '&v=' + encodeURIComponent(focus) : '')); },
    toggleLike: function (videoId) { return http('POST', '/videos/' + videoId + '/like'); },
    toggleSave: function (videoId) { return http('POST', '/videos/' + videoId + '/save'); },
    share: function (videoId, channel) { return http('POST', '/videos/' + videoId + '/share', { channel: channel }); },
    comments: function (videoId) { return http('GET', '/videos/' + videoId + '/comments'); },
    addComment: function (videoId, text) { return http('POST', '/videos/' + videoId + '/comments', { text: text }); },
    toggleCommentLike: function (commentId) { return http('POST', '/comments/' + commentId + '/like'); },
    toggleFollow: function (userId) { return http('POST', '/users/' + userId + '/follow'); },
    user: function (userId) { return http('GET', '/users/' + userId); },
    userVideos: function (userId) { return http('GET', '/users/' + userId + '/videos'); },
    search: function (q, filter) { return http('GET', '/search?q=' + encodeURIComponent(q) + '&filter=' + filter); },
    explore: function () { return http('GET', '/explore'); },
    matches: function (tab) { return http('GET', '/matches?tab=' + tab); },
    match: function (id) { return http('GET', '/matches/' + id); },
    conversations: function () { return http('GET', '/conversations'); },
    conversation: function (id) { return http('GET', '/conversations/' + encodeURIComponent(id)); },
    openConversationWith: function (userId) { return http('POST', '/conversations', { user_id: userId }); },
    sendMessage: function (id, text) { return http('POST', '/conversations/' + encodeURIComponent(id) + '/messages', { text: text }); },
    notifications: function () { return http('GET', '/notifications'); },
    markNotificationsRead: function () { return http('POST', '/notifications/read'); },
    /* Publication : envoi en morceaux de 4 Mo vers le site (reprise possible en 3G), puis enregistrement */
    publish: function (data) {
      var C = Frikip.config, file = data.file, CH = 4 * 1024 * 1024, total = Math.ceil(file.size / CH);
      var id = Array.from(crypto.getRandomValues(new Uint8Array(16)), function (b) { return ('0' + (b % 36).toString(36)).slice(-1); }).join('') + Date.now().toString(36);
      id = id.replace(/[^a-z0-9]/gi, '').slice(0, 32);
      var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
      function part(i) {
        if (i >= total) return Promise.resolve();
        if (data.onProgress) data.onProgress(i / total);
        return fetch(C.chunkUrl, { method: 'POST', credentials: 'include', body: file.slice(i * CH, (i + 1) * CH),
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Upload-Id': id, 'X-Chunk-Index': String(i), 'X-Chunk-Total': String(total), 'X-File-Size': String(file.size), 'Content-Type': 'application/octet-stream' } })
          .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || 'Envoi interrompu.'); return part(i + 1); }); });
      }
      return part(0).then(function () {
        var f = new FormData();
        var cap = (data.caption || '').trim();
        f.append('title', cap.slice(0, 150)); if (cap.length > 150) f.append('caption', cap.slice(150, 270));
        f.append('upload_id', id); f.append('file_name', file.name || 'video.mp4');
        if (data.duration) f.append('duration', Math.floor(data.duration / 60) + ':' + ('0' + (data.duration % 60)).slice(-2));
        if (data.challenge) f.append('challenge_id', data.challenge);
        return fetch(C.publishUrl, { method: 'POST', credentials: 'include', body: f, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
          .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || 'Publication impossible.'); return d; }); });
      });
    },
    club: function (id) { return http('GET', '/clubs/' + id); },
    uploadPhoto: function (kind, file) { var f = new FormData(); f.append('kind', kind); f.append('image', file); return http('POST', '/me/photo', f, true); },
    lives: function () { return http('GET', '/lives'); },
    discover: function (topic, page) { return http('GET', '/discover?topic=' + encodeURIComponent(topic || '') + '&page=' + (page || 1)); },
    saved: function () { return http('GET', '/saved'); },
    // Pages du site (hors /api/v1) qui répondent en JSON
    site: function (method, url, body) {
      var opts = { method: method, credentials: 'include', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
      var csrf = document.querySelector('meta[name="csrf-token"]'); if (csrf) opts.headers['X-CSRF-TOKEN'] = csrf.content;
      if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
      return fetch(url, opts).then(function (r) {
        if (r.status === 401) { Frikip.bus.emit('auth:required'); throw new Error('Connecte-toi pour continuer.'); }
        return r.json().catch(function () { return {}; }).then(function (d) { if (!r.ok) throw new Error(d.error || d.message || 'Action impossible.'); return d; });
      });
    },
    block: function (id) { return remote.site('POST', L().block + '/' + encodeURIComponent(id)); },
    report: function (type, id, reason) { return remote.site('POST', L().report, { target_type: type, target_id: id, reason: reason }); },
    call: function (partner, kind) { return remote.site('POST', L().call + '/' + encodeURIComponent(partner), { kind: kind }); },
    roomSince: function (room, after) { return remote.site('GET', L().room + '/' + room + '?apres=' + (after || 0)).then(function (d) { return d.messages || []; }); },
    roomPost: function (room, body, kind) { return remote.site('POST', L().room + '/' + room, kind === 'react' ? { react: body } : { body: body }); },
    discoverPlay: function (dbId) { return remote.site('POST', L().discoverPlay + '/' + dbId + '/lecture').catch(function () {}); },
    view: function (v) { return remote.site('POST', L().view + '/' + v.id + '/vue', { athlete: v.author.id }).catch(function () {}); },
    watchTime: function (v, seconds) { return remote.site('POST', L().view + '/' + v.id + '/temps', { seconds: Math.round(seconds) }).catch(function () {}); }
  };
  function L() { return Frikip.config.links || {}; }

  // ------------------------------------------------------------ mode DÉMO (navigateur)
  var D = null;
  function db() {
    if (!D) {
      D = JSON.parse(JSON.stringify(Frikip.demo));   // copie : la démo repart propre à chaque chargement
    }
    return D;
  }
  var wait = function (v) { return new Promise(function (ok) { setTimeout(function () { ok(v); }, 120); }); };
  var byId = function (list, id) { for (var i = 0; i < list.length; i++) if (list[i].id === id) return list[i]; return null; };
  function userOut(u) {
    if (!u) return null;
    var d = db();
    return Object.assign({}, u, { isMe: u.id === d.meId, isFollowing: d.following.indexOf(u.id) > -1, club: u.clubId ? byId(d.clubs, u.clubId) : null });
  }
  function videoOut(v) {
    var d = db();
    return Object.assign({}, v, { author: userOut(byId(d.users, v.authorId)), liked: d.liked.indexOf(v.id) > -1, saved: d.saved.indexOf(v.id) > -1 });
  }
  function score(v) {   // ordre du fil « Pour toi » : engagement + fraîcheur
    var ageH = (Date.now() - new Date(v.createdAt).getTime()) / 36e5;
    return (v.likes * 2 + v.comments * 4 + v.shares * 6 + v.saves * 3) / Math.pow(ageH + 2, 1.2);
  }
  function tagsOf(text) { return (text.match(/#[\p{L}\p{N}_]+/gu) || []).map(function (t) { return t.toLowerCase(); }); }
  function matchOut(m) { var d = db(); return Object.assign({}, m, { homeClub: byId(d.clubs, m.home), awayClub: byId(d.clubs, m.away) }); }
  function convOut(c) {
    var d = db(), last = c.messages[c.messages.length - 1];
    return Object.assign({}, c, { with: userOut(byId(d.users, c.withId)), last: last });
  }

  var demo = {
    lives: function () {
      var d = db(), u = function (id) { return userOut(byId(d.users, id)); };
      return wait({
        members: [
          { id: 901, kind: 'membre', status: 'direct', title: 'Je commente Étoile – Lions en direct', viewers: 312, owner: u('u10'), room: 'l-901', url: '#' },
          { id: 902, kind: 'membre', status: 'direct', title: 'Séance de jongles en live', viewers: 128, owner: u('u9'), room: 'l-902', url: '#' }
        ],
        matches: [{ id: 903, kind: 'match', status: 'direct', title: 'Étoile de Kaloum – Lions de Dixinn', home: 'Étoile de Kaloum', away: 'Lions de Dixinn', hasScore: true, hs: 2, as: 1, minute: '68', viewers: 2140, room: 'l-903', url: '#' }],
        soon: [{ id: 904, kind: 'match', status: 'prevu', title: 'Kankan United – Nimba Stars', home: 'Kankan United', away: 'Nimba Stars', startsAt: new Date(Date.now() + 3 * 36e5).toISOString(), viewers: 0, room: 'l-904', url: '#' }],
        big: [], partner: null, canLive: true, liveRefusal: null, canBroadcast: false, links: {}
      });
    },
    discover: function () { return wait({ topics: [], items: [] }); },
    saved: function () { var d = db(); return wait(d.videos.filter(function (v) { return d.saved.indexOf(v.id) > -1; }).map(videoOut)); },
    block: function () { return wait({ blocked: true, message: 'Bloqué : tu ne vois plus ses contenus.' }); },
    report: function () { return wait({ ok: true }); },
    call: function () { return Promise.reject(new Error('Les appels marchent sur le vrai site.')); },
    _rooms: {},
    roomSince: function (room, after) {
      if (!demo._rooms[room]) demo._rooms[room] = [
        { id: 1, name: 'Sékou Bangoura', kind: 'text', body: 'Quelle ambiance au stade 🔥', at: '20:41' },
        { id: 2, name: 'Fatoumata Diallo', kind: 'react', body: '👏', at: '20:42' },
        { id: 3, name: 'Mory Keïta', kind: 'text', body: 'Le 2e but, quelle frappe !', at: '20:44' },
        { id: 4, name: 'Coach Alpha Touré', kind: 'text', body: 'Bon pressing de Kaloum depuis 10 minutes', at: '20:46' }];
      var r = demo._rooms[room]; return wait(r.filter(function (m) { return m.id > (after || 0); })); },
    roomPost: function (room, body, kind) {
      var r = demo._rooms[room] = demo._rooms[room] || [], me = byId(db().users, db().meId);
      var m = { id: r.length + 1, name: me.name, kind: kind || 'text', body: body, mine: true, at: new Date().toTimeString().slice(0, 5) };
      r.push(m); return wait({ ok: true, id: m.id });
    },
    discoverPlay: function () { return wait({}); },
    view: function () { return wait({}); },
    watchTime: function () { return wait({}); },
    me: function () { return wait(userOut(byId(db().users, db().meId))); },
    feed: function (tab) {
      var d = db(), list = d.videos.slice();
      if (tab === 'abonnements') list = list.filter(function (v) { return d.following.indexOf(v.authorId) > -1 || v.authorId === d.meId; })
        .sort(function (a, b) { return new Date(b.createdAt) - new Date(a.createdAt); });
      else list.sort(function (a, b) { return (b.fresh ? 1e12 : score(b)) - (a.fresh ? 1e12 : score(a)); });
      return wait({ items: list.map(videoOut), next: null });
    },
    toggleLike: function (id) {
      var d = db(), v = byId(d.videos, id), i = d.liked.indexOf(id);
      if (i > -1) { d.liked.splice(i, 1); v.likes--; } else { d.liked.push(id); v.likes++; }
      return wait({ liked: i < 0, likes: v.likes });
    },
    toggleSave: function (id) {
      var d = db(), v = byId(d.videos, id), i = d.saved.indexOf(id);
      if (i > -1) { d.saved.splice(i, 1); v.saves--; } else { d.saved.push(id); v.saves++; }
      return wait({ saved: i < 0, saves: v.saves });
    },
    share: function (id) { var v = byId(db().videos, id); v.shares++; return wait({ shares: v.shares }); },
    comments: function (id) {
      var d = db(), list = (d.comments[id] || []).slice().sort(function (a, b) { return (b.pinned ? 1 : 0) - (a.pinned ? 1 : 0) || b.likes - a.likes; });
      return wait(list.map(function (c) { return Object.assign({}, c, { author: userOut(byId(d.users, c.authorId)), liked: !!c.liked }); }));
    },
    addComment: function (id, text) {
      var d = db(), c = { id: 'k' + Date.now(), authorId: d.meId, text: text, likes: 0, createdAt: new Date().toISOString() };
      (d.comments[id] = d.comments[id] || []).push(c);
      byId(d.videos, id).comments++;
      return wait(Object.assign({}, c, { author: userOut(byId(d.users, d.meId)) }));
    },
    toggleCommentLike: function (cid) {
      var d = db(), found = null;
      Object.keys(d.comments).forEach(function (k) { d.comments[k].forEach(function (c) { if (c.id === cid) found = c; }); });
      if (!found) return wait({ liked: false, likes: 0 });
      found.liked = !found.liked; found.likes += found.liked ? 1 : -1;
      return wait({ liked: found.liked, likes: found.likes });
    },
    toggleFollow: function (uid) {
      var d = db(), u = byId(d.users, uid), me = byId(d.users, d.meId), i = d.following.indexOf(uid);
      if (i > -1) { d.following.splice(i, 1); u.followers--; me.following--; } else { d.following.push(uid); u.followers++; me.following++; }
      return wait({ following: i < 0, followers: u.followers });
    },
    user: function (uid) { return wait(userOut(byId(db().users, uid))); },
    userVideos: function (uid) {
      return wait(db().videos.filter(function (v) { return v.authorId === uid; }).sort(function (a, b) { return new Date(b.createdAt) - new Date(a.createdAt); }).map(videoOut));
    },
    explore: function () {
      var d = db(), count = {};
      d.videos.forEach(function (v) { tagsOf(v.caption).forEach(function (t) { count[t] = (count[t] || 0) + 1 + v.likes / 20000; }); });
      var trends = Object.keys(count).sort(function (a, b) { return count[b] - count[a]; }).slice(0, 8).map(function (t) {
        return { tag: t, posts: d.videos.filter(function (v) { return tagsOf(v.caption).indexOf(t) > -1; }).length };
      });
      var popular = d.videos.slice().sort(function (a, b) { return b.views - a.views; }).slice(0, 8).map(videoOut);
      var suggested = d.users.filter(function (u) { return u.id !== d.meId && d.following.indexOf(u.id) < 0; })
        .sort(function (a, b) { return b.followers - a.followers; }).slice(0, 6).map(userOut);
      return wait({ trends: trends, popular: popular, suggested: suggested });
    },
    search: function (q, filter) {
      var d = db(), n = q.trim().toLowerCase(), tag = n.charAt(0) === '#';
      var users = tag ? [] : d.users.filter(function (u) { return u.id !== d.meId && (u.name + ' ' + u.handle + ' ' + u.type + ' ' + u.city + ' ' + (u.player ? u.player.position : '')).toLowerCase().indexOf(n) > -1; });
      var clubs = tag ? [] : d.clubs.filter(function (c) { return (c.name + ' ' + c.city).toLowerCase().indexOf(n) > -1; });
      var vids = d.videos.filter(function (v) { return tag ? tagsOf(v.caption).indexOf(n) > -1 : v.caption.toLowerCase().indexOf(n) > -1; });
      if (filter === 'joueurs') users = users.filter(function (u) { return u.type === 'Joueur'; });
      return wait({
        users: filter === 'acteurs' ? users.filter(function (u) { return ['Joueur', 'Fan', 'Club', 'Recruteur'].indexOf(u.type) < 0; }).map(userOut)
          : filter === 'tout' || filter === 'joueurs' || filter === 'comptes' ? users.map(userOut) : [],
        clubs: filter === 'tout' || filter === 'clubs' ? clubs : [],
        videos: filter === 'tout' || filter === 'videos' ? vids.map(videoOut) : []
      });
    },
    matches: function (tab) {
      var d = db(), list = d.matches.filter(function (m) {
        return tab === 'direct' ? m.status === 'live' : tab === 'avenir' ? m.status === 'upcoming' : tab === 'resultats' ? m.status === 'finished' : true;
      });
      return wait(list.map(matchOut));
    },
    match: function (id) { return wait(matchOut(byId(db().matches, id))); },
    conversations: function () { return wait(db().conversations.map(convOut).sort(function (a, b) { return new Date(b.last.at) - new Date(a.last.at); })); },
    conversation: function (id) { var c = byId(db().conversations, id); c.unread = 0; return wait(convOut(c)); },
    openConversationWith: function (uid) {
      var d = db(), c = d.conversations.filter(function (x) { return x.withId === uid; })[0];
      if (!c) { c = { id: 't' + Date.now(), withId: uid, unread: 0, messages: [] }; d.conversations.push(c); }
      return wait(convOut(c));
    },
    sendMessage: function (id, text) {
      var c = byId(db().conversations, id), m = { from: db().meId, text: text, at: new Date().toISOString() };
      c.messages.push(m);
      return wait(m);
    },
    /* Démo seulement : réponse automatique pour montrer la réception d'un message */
    _fakeReply: function (id) {
      var c = byId(db().conversations, id);
      var lines = ['Bien reçu 👍', 'On en parle après l’entraînement.', 'Top, merci !', 'Envoie-moi la vidéo complète.'];
      var m = { from: c.withId, text: lines[Math.floor(Math.random() * lines.length)], at: new Date().toISOString() };
      c.messages.push(m);
      return m;
    },
    notifications: function () {
      var d = db();
      return wait(d.notifications.map(function (n) { return Object.assign({}, n, { actor: n.actorId ? userOut(byId(d.users, n.actorId)) : null }); }));
    },
    markNotificationsRead: function () { db().notifications.forEach(function (n) { n.read = true; }); return wait({ ok: true }); },
    publish: function (data) {
      var d = db(), v = {
        id: 'v' + Date.now(), authorId: d.meId, caption: data.caption || '', sound: 'Son original · ' + byId(d.users, d.meId).name,
        scene: 'pitch', hue: '#1FE064', localUrl: data.file ? URL.createObjectURL(data.file) : null,
        likes: 0, comments: 0, shares: 0, saves: 0, views: 0, duration: data.duration || 0, createdAt: new Date().toISOString(), fresh: true
      };
      d.videos.unshift(v);
      return wait(videoOut(v));
    },
    club: function (id) { return wait(byId(db().clubs, id)); },
    uploadPhoto: function (kind, file) {
      var u = byId(db().users, db().meId), url = URL.createObjectURL(file); u[kind] = url;
      return wait({ url: url });
    },
    unreadCounts: function () {
      var d = db();
      return {
        messages: d.conversations.reduce(function (s, c) { return s + (c.unread || 0); }, 0),
        notifications: d.notifications.filter(function (n) { return !n.read; }).length
      };
    },
    /* Démo : fait avancer les matchs en direct (en mode api, le serveur s'en charge) */
    _tick: function () {
      var changed = [];
      db().matches.forEach(function (m) {
        if (m.status !== 'live') return;
        m.minute = Math.min(m.minute + 1, 90);
        if (m.minute === 90) m.status = 'finished';
        changed.push(m.id);
      });
      return changed;
    }
  };

  Frikip.api = Frikip.config.mode === 'api' ? remote : demo;
  if (Frikip.config.mode === 'api') {
    // compteurs non lus : en mode api, l'écran les reçoit dans /me (champs unread_messages et unread_notifications)
    remote.unreadCounts = function () { return Frikip.state.counts || { messages: 0, notifications: 0 }; };
    remote.openConversationWith = function (uid) { return http('POST', '/conversations', { user_id: uid }); };
    remote._tick = function () { return []; };
    remote._fakeReply = function () { return null; };
  }
})();
