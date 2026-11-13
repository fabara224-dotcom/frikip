/*
 * ACCUEIL — fil d'actualité (comme la maquette) : stories en haut, publications en cartes,
 * vidéos qui se lancent toutes seules (sans le son) quand elles apparaissent à l'écran.
 * Toucher une vidéo ouvre le mode plein écran façon TikTok (#reels).
 */
window.Frikip = window.Frikip || {};
Frikip.views = Frikip.views || {};
(function () {
  var U = Frikip.ui, api = Frikip.api, S = Frikip.state;
  var root, list, tab = 'pour-toi', items = [], observer = null, playing = null;
  var seen = {};
  try { seen = JSON.parse(localStorage.getItem('frikip.storiesSeen') || '{}'); } catch (e) { seen = {}; }

  var HEART = '<svg class="ci heart" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.6-4.7-9.6-9.4C1 8.2 3.2 4.5 7 4.5c2.1 0 3.8 1.1 5 2.8 1.2-1.7 2.9-2.8 5-2.8 3.8 0 6 3.7 4.6 7.1C19.6 16.3 12 21 12 21z"/></svg>';
  var DOTS = '<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="19" cy="12" r="1.6" fill="currentColor"/></svg>';
  var PLAY = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="#fff"/></svg>';

  function dur(s) { s = Math.round(s || 0); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }

  var storyGroups = [], lastFeed = [], lastLives = [];
  function refreshStories() {
    api.stories().then(function (d) { var el = root && root.querySelector('[data-stories]'); if (el) el.innerHTML = stories(lastFeed, lastLives, d.groups); });
  }
  function stories(vids, lives, groups) {
    var me = S.me;
    storyGroups = (groups || []).slice().sort(function (x, y) { return (y.mine - x.mine) || (x.seen - y.seen); });
    var mine = storyGroups.filter(function (g) { return g.mine; })[0];
    var liveHtml = (lives || []).slice(0, 8).map(function (l) {
      var who = l.owner || { name: l.home || l.title, color: '#E5373A' };
      return '<a class="story live" role="listitem" href="' + U.esc(l.url) + '" aria-label="Live : ' + U.esc(l.title) + '">' +
        '<span class="story-ring live">' + U.avatar(who, 'story') + '<span class="story-live">LIVE</span></span><span class="story-name">' + U.esc((who.name || '').split(' ')[0]) + '</span></a>';
    });
    // ordre : ta story, les stories (non vues d'abord), puis les lives
    var liveItems = '', liveRest = liveHtml.join('');
    var others;
    if (storyGroups.length) {
      others = storyGroups.map(function (g, k) {
        if (g.mine) return '';
        var u = { name: g.name, color: g.color, photo: g.avatar };
        return '<button class="story" role="listitem" type="button" data-story-group="' + k + '" aria-label="Story de ' + U.esc(g.name) + '">' +
          '<span class="story-ring' + (g.seen ? ' seen' : '') + '">' + U.avatar(u, 'story') + '</span><span class="story-name">' + U.esc((g.name || '').split(' ')[0]) + '</span></button>';
      }).join('');
    } else {   // pas encore de story : on montre les comptes qui viennent de publier
      var by = {}, order = [];
      vids.forEach(function (v) { if (v.source !== 'youtube' && !by[v.author.id]) { by[v.author.id] = v; order.push(v.author.id); } });
      others = order.slice(0, 14).map(function (id) {
        var v = by[id], a = v.author;
        return '<button class="story" role="listitem" type="button" data-story="' + v.id + '" data-author="' + a.id + '" aria-label="Dernière vidéo de ' + U.esc(a.name) + '">' +
          '<span class="story-ring' + (seen[v.id] ? ' seen' : '') + '">' + U.avatar(a, 'story') + '</span><span class="story-name">' + U.esc(a.name.split(' ')[0]) + '</span></button>';
      }).join('');
    }
    var myIdx = mine ? storyGroups.indexOf(mine) : -1;
    return '<div class="stories" role="list" aria-label="Stories et lives">' +
      '<div class="story" role="listitem"><button class="story-me" type="button" ' + (mine ? 'data-story-group="' + myIdx + '" aria-label="Voir ta story"' : 'data-new-story aria-label="Ajouter une story"') + '>' +
        '<span class="story-ring' + (mine ? (mine.seen ? ' seen' : '') : ' create') + '">' + (me ? U.avatar(me, 'story') : '') + '</span></button>' +
        '<button class="story-plus" type="button" data-new-story aria-label="Ajouter une story">' + U.icon('plus') + '</button>' +
        '<span class="story-name">Ta story</span></div>' + liveItems + others + liveRest + '</div>';
  }

  function media(v) {
    var src = v.url || v.localUrl;
    var m = src
      ? '<video src="' + U.esc(src) + '"' + (v.poster ? ' poster="' + U.esc(v.poster) + '"' : '') + ' muted playsinline loop preload="metadata" aria-hidden="true"></video>'
      : '<canvas data-scene="' + v.id + '" aria-hidden="true"></canvas>';
    return '<button class="post-media" type="button" data-open="' + v.id + '" aria-label="Regarder la vidéo en plein écran">' + m +
      '<span class="post-play">' + PLAY + '</span><span class="post-dur num">' + PLAY + dur(v.duration) + '</span>' +
      '<span class="post-views num">' + U.n(v.views) + ' vues</span></button>';
  }

  function ytPost(v) {
    var a = v.author;
    return '<article class="post" data-id="' + v.id + '">' +
      '<header class="post-h"><span class="av av-post yt-av" aria-hidden="true">' + U.icon('play') + '</span>' +
        '<div class="post-who"><b>' + U.esc(a.name) + '</b><small>Découvrir · ' + U.esc(v.topic || 'YouTube') + '</small></div></header>' +
      '<p class="post-text">' + U.esc(v.caption) + '</p>' +
      '<button class="post-media" type="button" data-open="' + v.id + '" aria-label="Regarder la vidéo"><img src="' + U.esc(v.poster) + '" alt="" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">' +
        '<span class="post-play">' + PLAY + '</span>' + (v.duration ? '<span class="post-dur num">' + PLAY + dur(v.duration) + '</span>' : '') + '<span class="post-views num">YouTube</span></button></article>';
  }

  function post(v) {
    if (v.source === 'youtube') return ytPost(v);
    var a = v.author;
    return '<article class="post" data-id="' + v.id + '">' +
      '<header class="post-h"><button type="button" data-action="profile" data-user="' + a.id + '" aria-label="Profil de ' + U.esc(a.name) + '">' + U.avatar(a, 'post') + '</button>' +
        '<div class="post-who"><button type="button" data-action="profile" data-user="' + a.id + '"><b>' + U.name(a) + '</b></button>' +
        '<small>' + U.esc([a.city, a.type === 'Joueur' && a.player ? a.player.position : a.type].filter(Boolean).join(' · ')) + '</small></div>' +
        (a.isMe || a.isFollowing || a.canFollow === false ? '' : '<button class="pill-follow" type="button" data-action="follow" data-user="' + a.id + '" aria-pressed="false">Suivre</button>') +
        '<button class="post-more" type="button" data-more="' + v.id + '" aria-label="Plus d’options">' + DOTS + '</button></header>' +
      media(v) +
      '<footer class="post-actions">' +
        '<button type="button" data-like aria-pressed="' + v.liked + '" aria-label="J’aime">' + HEART + '</button>' +
        '<button type="button" data-comments aria-label="Commentaires">' + U.icon('comment') + '</button>' +
        '<button type="button" data-share aria-label="Partager">' + U.icon('plane') + '<span class="sr-only num" data-c="shares">' + U.n(v.shares) + '</span></button>' +
        '<button type="button" data-save aria-pressed="' + v.saved + '" aria-label="Enregistrer" class="post-save">' + U.icon('bookmark') + '</button>' +
      '</footer>' +
      '<div class="post-meta"><b class="post-likes" data-like-line><span class="num" data-c="likes">' + U.n(v.likes) + '</span> J’aime</b>' +
        '<p class="post-text"><b>' + U.esc((a.handle || a.name || '').toString()) + '</b> ' + U.caption(v.caption) + '</p>' +
        (v.comments ? '<button type="button" class="post-cmts" data-comments>Voir les ' + U.n(v.comments) + ' commentaires</button>' : '') +
        '<time class="post-time">' + U.ago(v.createdAt) + '</time></div>' +
      '</article>';
  }

  function liveCard(m) {
    return '<button class="live-post" type="button" data-action="match" data-match="' + m.id + '">' +
      '<span class="live-top"><span class="pill-live"><i class="pulse"></i>Live</span><span>' + U.esc(m.comp) + '</span><span class="num" data-home-min="' + m.id + '">' + m.minute + '’</span></span>' +
      '<span class="live-row"><span class="tm">' + U.crest(m.homeClub, 46) + '<b>' + U.esc(m.homeClub.name) + '</b></span>' +
      '<span class="sc num" data-home-score="' + m.id + '">' + m.hs + ' – ' + m.as + '</span>' +
      '<span class="tm">' + U.crest(m.awayClub, 46) + '<b>' + U.esc(m.awayClub.name) + '</b></span></span>' +
      '<span class="live-cta">Suivre le match en direct ' + U.icon('back', 'flip') + '</span></button>';
  }

  function skeleton() {
    var one = '<div class="post skel"><div class="post-h"><i class="sk sk-av"></i><div style="flex:1"><i class="sk sk-l"></i><i class="sk sk-s"></i></div></div><i class="sk sk-t"></i><i class="sk sk-m"></i></div>';
    return one + one;
  }

  function render() {
    root.innerHTML =
      '<header class="home-top">' +
        '<button class="feed-switch" type="button" data-feed-menu aria-expanded="false" aria-label="Choisir le fil">' + U.logoMark() + U.icon('back', 'chev') + '</button>' +
        '<span class="ht-side"><a class="iconbtn" href="#notifications" aria-label="Notifications">' + U.icon('heart') + '<span class="dot-badge num" data-badge="notifications" hidden></span></a>' +
        '<a class="iconbtn" href="#messages" aria-label="Messages">' + U.icon('messenger') + '<span class="dot-badge num" data-badge="messages" hidden></span></a></span></header>' +
      '<div class="home-tabs" role="group" aria-label="Fil" data-feed-tabs>' +
        '<button type="button" data-tab="pour-toi" aria-pressed="' + (tab === 'pour-toi') + '">Pour vous</button>' +
        '<button type="button" data-tab="abonnements" aria-pressed="' + (tab === 'abonnements') + '">Abonnements</button>' +
        '<a href="#reels" class="home-reels">' + U.icon('reels') + 'Reels</a>' +
      '</div>' +
      '<div data-stories></div><div class="posts" data-list>' + skeleton() + '</div>';
    list = root.querySelector('[data-list]');
    load();
  }

  function load() {
    Frikip.scene.stop();
    var livesP = api.lives ? api.lives().catch(function () { return { members: [], matches: [] }; }) : Promise.resolve({ members: [], matches: [] });
    var storiesP = api.stories ? api.stories().catch(function () { return { groups: [] }; }) : Promise.resolve({ groups: [] });
    Promise.all([api.feed(tab), tab === 'pour-toi' ? api.matches('direct') : Promise.resolve([]), api.feed('pour-toi'), livesP, storiesP]).then(function (r) {
      items = r[0].items;
      lastFeed = r[2].items; lastLives = (r[3].members || []).concat(r[3].matches || []);
      root.querySelector('[data-stories]').innerHTML = stories(lastFeed, lastLives, r[4].groups);
      if (!items.length) {
        list.innerHTML = '<div class="post empty-post"><b>' + (tab === 'abonnements' ? 'Rien de neuf dans tes abonnements.' : 'Pas encore de publication.') + '</b>' +
          '<span>' + (tab === 'abonnements' ? 'Abonne-toi à des joueurs et des clubs pour voir leurs vidéos ici.' : 'Publie la première vidéo foot de FootProWorld.') + '</span>' +
          '<a class="btn btn-green" href="' + (tab === 'abonnements' ? '#explorer' : '#publier') + '">' + (tab === 'abonnements' ? 'Trouver des comptes' : 'Publier une vidéo') + '</a></div>';
        return;
      }
      var html = items.map(post);
      if (r[1].length) html.splice(Math.min(1, html.length), 0, liveCard(r[1][0]));
      list.innerHTML = html.join('');
      list.querySelectorAll('canvas[data-scene]').forEach(function (c) { var v = byId(c.getAttribute('data-scene')); if (v) Frikip.scene.still(c, v); });
      watch();
    });
  }

  function byId(id) { for (var i = 0; i < items.length; i++) if (items[i].id === id) return items[i]; return null; }

  // Lecture automatique (sans le son) de la vidéo la plus visible
  function watch() {
    if (observer) observer.disconnect();
    observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        var m = e.target, vid = m.querySelector('video'), cv = m.querySelector('canvas');
        if (e.isIntersecting && e.intersectionRatio > .65) {
          if (playing && playing !== m) stop(playing);
          playing = m; m.classList.add('on');
          if (vid) vid.play().catch(function () {});
          else if (cv) Frikip.scene.play(cv, byId(cv.getAttribute('data-scene')));
        } else if (playing === m) stop(m);
      });
    }, { root: root, threshold: [.65] });
    list.querySelectorAll('.post-media').forEach(function (m) { observer.observe(m); });
  }
  function stop(m) {
    m.classList.remove('on');
    var vid = m.querySelector('video'); if (vid) vid.pause();
    if (m.querySelector('canvas')) Frikip.scene.stop();
    if (playing === m) playing = null;
  }

  function onClick(e) {
    var t;
    if ((t = e.target.closest('[data-feed-menu]'))) {
      var open = !root.classList.contains('feed-open'); root.classList.toggle('feed-open', open); t.setAttribute('aria-expanded', open); return;
    }
    if ((t = e.target.closest('[data-tab]'))) {
      root.classList.remove('feed-open');
      tab = t.getAttribute('data-tab');
      root.querySelectorAll('[data-tab]').forEach(function (b) { b.setAttribute('aria-pressed', b === t); });
      list.innerHTML = skeleton(); load(); return;
    }
    if (e.target.closest('[data-new-story]')) { Frikip.stories.create(); return; }
    if ((t = e.target.closest('[data-story-group]'))) {
      Frikip.stories.open(storyGroups, +t.getAttribute('data-story-group'), function () { var el = root.querySelector('[data-stories]'); el.innerHTML = stories(lastFeed, lastLives, storyGroups); });
      return;
    }
    if (e.target.closest('[data-create]')) { Frikip.views.create(); return; }
    if ((t = e.target.closest('[data-story]'))) {
      var vid = t.getAttribute('data-story'); seen[vid] = 1;
      try { localStorage.setItem('frikip.storiesSeen', JSON.stringify(seen)); } catch (x) { /* facultatif */ }
      t.querySelector('.story-ring').classList.add('seen');
      Frikip.views.reels.focusVideo(vid, byId(vid)); location.hash = 'reels'; return;
    }
    if ((t = e.target.closest('[data-open]'))) { var oid = t.getAttribute('data-open'); Frikip.views.reels.focusVideo(oid, byId(oid)); location.hash = 'reels'; return; }
    var card = e.target.closest('.post'); if (!card || !card.getAttribute('data-id')) return;
    var v = byId(card.getAttribute('data-id'));
    if ((t = e.target.closest('[data-like]'))) {
      api.toggleLike(v.id).then(function (r) { v.liked = r.liked; v.likes = r.likes; t.setAttribute('aria-pressed', r.liked); card.querySelector('[data-c="likes"]').textContent = U.n(r.likes); });
    } else if ((t = e.target.closest('[data-save]'))) {
      api.toggleSave(v.id).then(function (r) { v.saved = r.saved; t.setAttribute('aria-pressed', r.saved); U.toast(r.saved ? 'Ajouté à tes favoris' : 'Retiré de tes favoris'); });
    } else if (e.target.closest('[data-comments]')) {
      Frikip.views.reels.focusVideo(v.id); location.hash = 'reels';
      setTimeout(function () { var b = document.querySelector('[data-view="reels"] .clip [data-action="comments"]'); if (b) b.click(); }, 700);
    } else if ((t = e.target.closest('[data-share]'))) {
      var link = Frikip.config.shareBase + v.id;
      if (navigator.clipboard) navigator.clipboard.writeText(link).catch(function () {});
      api.share(v.id, 'lien').then(function (r) { var c = t.querySelector('[data-c]'); if (c) c.textContent = U.n(r.shares); });
      U.toast('Lien copié : ' + link);
    } else if (e.target.closest('[data-more]')) {
      U.sheet('Options', '<div class="menu-list" style="margin:0;border:0">' +
        '<button class="menu-item" type="button" data-opt="profil">' + U.icon('user') + '<span>Voir le profil de ' + U.esc(v.author.name) + '</span></button>' +
        '<button class="menu-item" type="button" data-opt="lien">' + U.icon('link') + '<span>Copier le lien</span></button>' +
        '<button class="menu-item" type="button" data-opt="signaler">' + U.icon('flag') + '<span>Signaler la publication</span></button>' +
        (v.author.isMe ? '' : '<button class="menu-item danger" type="button" data-opt="bloquer">' + U.icon('close') + '<span>Bloquer ' + U.esc(v.author.name) + '</span></button>') + '</div>', function (sh) {
        sh.addEventListener('click', function (x) {
          var o = x.target.closest('[data-opt]'); if (!o) return; U.closeSheet();
          var k = o.getAttribute('data-opt');
          if (k === 'profil') { S.profileId = v.author.id; location.hash = v.author.isMe ? 'profil' : 'compte'; if (location.hash === '#compte') Frikip.views.compte.show(); }
          else if (k === 'lien') { if (navigator.clipboard) navigator.clipboard.writeText(Frikip.config.shareBase + v.id).catch(function () {}); U.toast('Lien copié'); }
          else if (!S.me) Frikip.bus.emit('auth:required');
          else if (k === 'bloquer') api.block(v.author.id).then(function (r) { U.toast(r.message || 'Bloqué'); list.innerHTML = skeleton(); load(); }).catch(function (x) { U.toast(x.message); });
          else api.report('video', v.id, 'Publication signalée depuis l’accueil').then(function () { U.toast('Merci, la publication est envoyée à la modération.'); }).catch(function (x) { U.toast(x.message); });
        });
      });
    }
  }

  Frikip.views.accueil = {
    mount: function (el) { root = el; render(); el.addEventListener('click', onClick); Frikip.bus.on('stories:changed', refreshStories); },
    show: function () { if (list && list.querySelector('.post-media')) watch(); },
    hide: function () { if (playing) stop(playing); if (observer) observer.disconnect(); },
    reload: function () { if (root) { list.innerHTML = skeleton(); load(); } },
    tick: function (ids) {
      if (!root) return;
      ids.forEach(function (id) {
        var el = root.querySelector('[data-home-min="' + id + '"]'); if (!el) return;
        api.match(id).then(function (m) { el.textContent = m.status === 'live' ? m.minute + '’' : 'Terminé'; });
      });
    }
  };
})();
