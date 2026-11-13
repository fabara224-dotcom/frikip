/*
 * Petits éléments réutilisés par tous les écrans : icônes, avatars, écussons,
 * formats (nombres, dates), notifications éphémères (toast) et feuilles du bas.
 */
window.Frikip = window.Frikip || {};
(function () {
  var esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  };

  var P = {
    plane: '<path d="M21.5 2.5 2.5 9.3l7.4 3.3 3.4 8.9z"/><path d="M21.5 2.5 9.9 12.6"/>',
    plusSq: '<rect x="3" y="3" width="18" height="18" rx="5"/><path d="M12 7.5v9M7.5 12h9"/>',
    reels: '<rect x="3" y="3" width="18" height="18" rx="5"/><path d="M3 8.5h18M9.2 3l3 5.5M15.2 3l3 5.5"/><path d="M10.2 12.2v5.6l4.8-2.8z"/>',
    messenger: '<path d="M12 2.8c-5.2 0-9.2 3.8-9.2 8.8 0 2.7 1.1 5 3 6.6V21l2.8-1.5c1 .3 2.2.5 3.4.5 5.2 0 9.2-3.8 9.2-8.8S17.2 2.8 12 2.8z"/><path d="m7 14 3.5-3.8 2.3 2L17 8.3l-3.5 3.8-2.3-2z"/>',
    eye: '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    volume: '<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z"/><path d="M16 9a4 4 0 0 1 0 6M18.5 6.5a7.5 7.5 0 0 1 0 11"/>',
    home: '<path class="fillable" d="M3 10.4 12 3l9 7.4V21h-6.2v-6.6H9.2V21H3z"/>',
    search: '<circle cx="10.5" cy="10.5" r="7.5"/><path d="M16.2 16.2 21 21"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    ball: '<circle cx="12" cy="12" r="9"/><path d="M12 7l4 3-1.5 5h-5L8 10z"/><path d="M12 3v4M16 10l4.5-1.5M14.5 15l2.5 4M9.5 15L7 19M8 10L3.5 8.5"/>',
    msg: '<path d="M12 2.8c-5.2 0-9.2 3.8-9.2 8.8 0 2.7 1.1 5 3 6.6V21l2.8-1.5c1 .3 2.2.5 3.4.5 5.2 0 9.2-3.8 9.2-8.8S17.2 2.8 12 2.8z"/><path d="m7 14 3.5-3.8 2.3 2L17 8.3l-3.5 3.8-2.3-2z"/>',
    user: '<circle cx="12" cy="12" r="9.5"/><circle cx="12" cy="10" r="3.2"/><path d="M6.4 18.4c1.3-2.2 3.3-3.3 5.6-3.3s4.3 1.1 5.6 3.3"/>',
    bell: '<path d="M16.8 3.5c-1.9 0-3.6 1-4.8 2.7-1.2-1.7-2.9-2.7-4.8-2.7C4.3 3.5 2 5.9 2 8.9c0 5.9 8.6 11.1 10 11.6 1.4-.5 10-5.7 10-11.6 0-3-2.3-5.4-5.2-5.4z"/>',
    heart: '<path class="heart" d="M16.8 3.5c-1.9 0-3.6 1-4.8 2.7-1.2-1.7-2.9-2.7-4.8-2.7C4.3 3.5 2 5.9 2 8.9c0 5.9 8.6 11.1 10 11.6 1.4-.5 10-5.7 10-11.6 0-3-2.3-5.4-5.2-5.4z"/>',
    comment: '<path d="M20.6 16.6A9 9 0 1 0 17.2 20l4.3 1.4z"/>',
    share: '<path d="M14 5l7 7-7 7v-4c-5 0-8 1.5-11 5 1-6 4-10 11-11z"/>',
    bookmark: '<path class="bookmark" d="M19 21l-7-6.2L5 21V3h14z"/>',
    music: '<path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
    back: '<path d="M15 5l-7 7 7 7"/>',
    close: '<path d="M6 6l12 12M18 6L6 18"/>',
    send: '<path d="M4 12l16-8-6 16-2-7z"/>',
    play: '<path d="M7 4l13 8-13 8z"/>',
    trophy: '<path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4M12 14v4M8 21h8"/>',
    shield: '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
    film: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v16M17 4v16M3 9h4M17 9h4M3 15h4M17 15h4"/>',
    whistle: '<circle cx="9" cy="14" r="5"/><path d="M13 11l8-4M13 14h8"/>',
    dumbbell: '<path d="M6 7v10M18 7v10M3 10v4M21 10v4M6 12h12"/>',
    users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.5 3.1-5.5 6.5-5.5s5.9 2 6.5 5.5"/><circle cx="17" cy="9" r="2.8"/><path d="M16.5 14.5c2.8 0 4.6 1.6 5 4.5"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    star: '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
    link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    whatsapp: '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z"/><path d="M9 9c0 3 3 6 6 6l1.2-1.6-2-1-1 1c-1-.5-2-1.5-2.5-2.5l1-1-1-2z"/>',
    check: '<path d="M5 12l5 5 9-10"/>',
    flag: '<path d="M5 21V4h11l-2 4 2 4H5"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    upload: '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
    stats: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    logout: '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
    gift: '<rect x="3" y="9" width="18" height="12" rx="1"/><path d="M3 13h18M12 9v12M12 9c-2-4-6-4-6-1.5S9 9 12 9zM12 9c2-4 6-4 6-1.5S15 9 12 9z"/>',
    party: '<path d="M4 20l5-14 9 9z"/><path d="M14 4l1 2M19 6l-2 1M20 11l-2-.5"/>',
    receipt: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
    phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
    camera: '<rect x="2" y="6" width="14" height="12" rx="2"/><path d="M16 10l6-3v10l-6-3z"/>'
  };
  function icon(name, cls) { return '<svg class="i ' + (cls || '') + '" viewBox="0 0 24 24" aria-hidden="true">' + (P[name] || '') + '</svg>'; }

  var VERIF = '<svg class="verif" viewBox="0 0 24 24" role="img" aria-label="Compte vérifié"><path fill="#1FE064" d="M12 2l2.4 2.1 3.2-.3.9 3.1 2.8 1.6-1.1 3 1.1 3-2.8 1.6-.9 3.1-3.2-.3L12 22l-2.4-2.1-3.2.3-.9-3.1L2.7 15.5l1.1-3-1.1-3 2.8-1.6.9-3.1 3.2.3z"/><path d="M8 12.2l2.7 2.6L16.3 9" stroke="#03140A" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  function initials(name) {
    return String(name).replace(/^(Dr|Coach)\s+/i, '').split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0); }).join('').toUpperCase();
  }
  function avatar(u, size, ring) {
    if (!u) return '';
    var cls = 'av ' + (size ? 'av-' + size : '') + (ring ? ' ring' : '');
    if (u.photo) return '<span class="' + cls + ' has-photo" aria-hidden="true"><img src="' + esc(u.photo) + '" alt="" loading="lazy"></span>';
    return '<span class="' + cls + '" style="background:' + esc(u.color || '#2a2f2c') + '" aria-hidden="true">' + esc(initials(u.name)) + '</span>';
  }
  function crest(c, size) {
    if (!c) return '';
    var s = size || 40;
    if (c.logo) return '<img class="crest" src="' + esc(c.logo) + '" alt="' + esc(c.name) + '" style="width:' + s + 'px;height:' + s + 'px;object-fit:contain" loading="lazy">';
    var a = c.colors[0], b = c.colors[1];
    var dark = (function (hex) { var n = parseInt(hex.slice(1), 16); return ((n >> 16) * 299 + ((n >> 8) & 255) * 587 + (n & 255) * 114) / 1000 < 140; })(a);
    return '<svg class="crest" style="width:' + s + 'px;height:' + Math.round(s * 1.1) + 'px" viewBox="0 0 40 44" role="img" aria-label="Écusson ' + esc(c.name) + '">' +
      '<path d="M20 1l17 5v13c0 11-7.5 19-17 24C10.5 38 3 30 3 19V6z" fill="' + b + '" stroke="' + a + '" stroke-width="2.4"/>' +
      '<path d="M20 6l12 3.6V19c0 8-5 14-12 18z" fill="' + a + '" opacity=".9"/>' +
      '<text x="20" y="26" text-anchor="middle" font-family="Big Shoulders Display, Arial Narrow, sans-serif" font-weight="900" font-size="11.5" fill="' + (dark ? '#fff' : '#0b0b0b') + '" stroke="' + b + '" stroke-width=".6" paint-order="stroke">' + esc(c.short) + '</text></svg>';
  }

  function n(v) {
    v = Number(v) || 0;
    if (v >= 1e6) return (v / 1e6).toFixed(v >= 1e7 ? 0 : 1).replace('.', ',').replace(',0', '') + ' M';
    if (v >= 1e3) return (v / 1e3).toFixed(v >= 1e5 ? 0 : 1).replace('.', ',').replace(',0', '') + ' k';
    return String(v);
  }
  function ago(iso) {
    var s = (Date.now() - new Date(iso).getTime()) / 1000;
    if (s < 60) return 'à l’instant';
    if (s < 3600) return 'il y a ' + Math.floor(s / 60) + ' min';
    if (s < 86400) return 'il y a ' + Math.floor(s / 3600) + ' h';
    var d = Math.floor(s / 86400);
    return d === 1 ? 'hier' : 'il y a ' + d + ' j';
  }
  function hhmm(iso) { var d = new Date(iso); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
  function caption(text) {   // rend les #hashtags et @mentions cliquables
    return esc(text).replace(/(#[\p{L}\p{N}_]+)/gu, '<button class="tag" type="button" data-action="tag" data-tag="$1">$1</button>');
  }
  function ptype(u) { return '<span class="ptype"><i aria-hidden="true"></i>' + esc(u.type) + (u.player ? ' · ' + esc(u.player.position) : '') + '</span>'; }
  function name(u) { return esc(u.name) + (u.verified ? ' ' + VERIF : ''); }
  function followBtn(u, small) {
    if (!u || u.isMe || u.canFollow === false) return '';
    return '<button class="btn ' + (small ? 'btn-sm ' : '') + 'btn-green follow" type="button" data-action="follow" data-user="' + esc(u.id) + '" aria-pressed="' + (u.isFollowing ? 'true' : 'false') + '">' + (u.isFollowing ? 'Abonné' : 'Suivre') + '</button>';
  }

  var toastTimer = null;
  function toast(text) {
    var old = document.querySelector('.toast'); if (old) old.remove();
    var t = document.createElement('div'); t.className = 'toast'; t.setAttribute('role', 'status'); t.textContent = text;
    document.body.appendChild(t);
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { t.remove(); }, 2300);
  }

  // Feuille du bas (commentaires, partage…). Fermeture : bouton, clic dehors, touche Échap.
  function sheet(title, bodyHtml, onReady) {
    closeSheet();
    var s = document.createElement('div');
    s.className = 'scrim';
    s.innerHTML = '<div class="sheet" role="dialog" aria-modal="true" aria-label="' + esc(title) + '"><div class="sheet-h"><h3>' + esc(title) + '</h3>' +
      '<button class="iconbtn" type="button" data-close aria-label="Fermer">' + icon('close') + '</button></div><div class="sheet-b">' + bodyHtml + '</div></div>';
    s.addEventListener('click', function (e) { if (e.target === s || e.target.closest('[data-close]')) closeSheet(); });
    s.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSheet(); });
    document.body.appendChild(s);
    var focusable = s.querySelector('input, textarea, button:not([data-close])') || s.querySelector('[data-close]');
    if (focusable) focusable.focus({ preventScroll: true });
    if (onReady) onReady(s.querySelector('.sheet'));
    return s;
  }
  function closeSheet() { var s = document.querySelector('.scrim'); if (s) s.remove(); }

  // Vignette d'une vidéo : image d'aperçu, sinon début de la vidéo, sinon scène dessinée (maquette)
  function thumbMedia(v) {
    var st = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover';
    if (v.poster) return '<img src="' + esc(v.poster) + '" alt="" style="' + st + '" loading="lazy">';
    var src = v.url || v.localUrl;
    if (src) return '<video src="' + esc(src) + '#t=0.5" muted playsinline preload="metadata" style="' + st + '" aria-hidden="true"></video>';
    return '<canvas data-thumb="' + esc(v.id) + '" aria-hidden="true"></canvas>';
  }

  /* logo : les « o » de FootProWorld sont des ballons */
  function logoMark() { return '<span class="ig-logo" role="img" aria-label="FootProWorld"><span aria-hidden="true">F<svg class="lb" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10.4" fill="#fff" stroke="currentColor" stroke-width="2.2"/><path d="M12 7.4l4.4 3.2-1.7 5.1H9.3l-1.7-5.1z" fill="currentColor"/><path d="M12 7.4V2.4M16.4 10.6l4.8-1.7M14.7 15.7l2.9 4.3M9.3 15.7l-2.9 4.3M7.6 10.6 2.8 8.9" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round"/></svg><svg class="lb" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10.4" fill="#fff" stroke="currentColor" stroke-width="2.2"/><path d="M12 7.4l4.4 3.2-1.7 5.1H9.3l-1.7-5.1z" fill="currentColor"/><path d="M12 7.4V2.4M16.4 10.6l4.8-1.7M14.7 15.7l2.9 4.3M9.3 15.7l-2.9 4.3M7.6 10.6 2.8 8.9" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round"/></svg>tPr<svg class="lb" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10.4" fill="#fff" stroke="currentColor" stroke-width="2.2"/><path d="M12 7.4l4.4 3.2-1.7 5.1H9.3l-1.7-5.1z" fill="currentColor"/><path d="M12 7.4V2.4M16.4 10.6l4.8-1.7M14.7 15.7l2.9 4.3M9.3 15.7l-2.9 4.3M7.6 10.6 2.8 8.9" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round"/></svg>W<svg class="lb" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10.4" fill="#fff" stroke="currentColor" stroke-width="2.2"/><path d="M12 7.4l4.4 3.2-1.7 5.1H9.3l-1.7-5.1z" fill="currentColor"/><path d="M12 7.4V2.4M16.4 10.6l4.8-1.7M14.7 15.7l2.9 4.3M9.3 15.7l-2.9 4.3M7.6 10.6 2.8 8.9" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round"/></svg>rld</span></span>'; }

  Frikip.ui = { logoMark: logoMark, thumbMedia: thumbMedia, esc: esc, icon: icon, avatar: avatar, crest: crest, n: n, ago: ago, hhmm: hhmm, caption: caption, ptype: ptype, name: name, followBtn: followBtn, toast: toast, sheet: sheet, closeSheet: closeSheet, VERIF: VERIF };
})();
