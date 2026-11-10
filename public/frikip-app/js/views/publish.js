/*
 * PUBLIER — choisir une vidéo sur le téléphone ou l'ordinateur, écrire la description, publier.
 * Dans la maquette, la vidéo reste dans le navigateur (aperçu réel) et apparaît en tête du fil.
 * En production : envoi en morceaux vers Laravel (route /api/v1/videos), comme le site actuel.
 */
window.Frikip = window.Frikip || {};
Frikip.views = Frikip.views || {};
(function () {
  var U = Frikip.ui, api = Frikip.api;
  var root, file = null, duration = 0;
  var SUGGEST = ['#Buts', '#Dribble', '#Entrainement', '#Freestyle', '#JeunesTalents', '#DéfiFrikip', '#FootFéminin', '#Guinée'];

  function render() {
    file = null; duration = 0;
    var me = Frikip.state.me;
    if (me && me.canPublish === false) {
      root.innerHTML = '<header class="topbar"><h1>Publier</h1></header><div class="page"><h1 class="page-title">Publier</h1>' +
        '<p class="no-result">Les comptes club et recruteur ne peuvent pas encore publier de vidéos. Cette fonction arrive bientôt.</p></div>';
      return;
    }
    root.innerHTML = '<header class="topbar"><button class="iconbtn" type="button" data-back aria-label="Retour">' + U.icon('back') + '</button><h1>Publier</h1></header>' +
      '<div class="page"><h1 class="page-title">Publier une vidéo</h1><form class="upload" data-up novalidate>' +
        '<div class="drop" data-drop><input id="up-file" type="file" accept="video/*" aria-label="Choisir une vidéo">' +
          '<div data-empty style="display:flex;flex-direction:column;align-items:center;gap:10px;pointer-events:none">' + U.icon('upload') +
            '<b>Choisis une vidéo</b><span class="hint">Format vertical conseillé · 3 minutes maximum · 300 Mo</span><span class="btn btn-green btn-sm">Parcourir</span></div></div>' +
        '<label class="field" for="up-caption">Description<textarea id="up-caption" rows="3" maxlength="300" placeholder="Raconte l’action, ajoute des #hashtags…"></textarea>' +
          '<span class="hint" data-left>300 caractères restants</span></label>' +
        '<div class="chip-row" role="group" aria-label="Hashtags suggérés">' + SUGGEST.map(function (t) { return '<button class="chip" type="button" data-addtag="' + t + '">' + t + '</button>'; }).join('') + '</div>' +
        '<label class="field" for="up-vis">Qui peut voir la vidéo ?<select id="up-vis"><option value="public">Tout le monde</option><option value="followers">Mes abonnés</option><option value="private">Moi seulement</option></select></label>' +
        '<label class="field" for="up-chal">Participer à un défi<select id="up-chal"><option value="">Aucun</option><option value="sombrero">Défi de la semaine : le plus beau sombrero</option></select></label>' +
        '<p class="hint" data-err role="alert" style="color:var(--live);margin:0"></p>' +
        '<button class="btn btn-green" type="submit" data-submit>Publier</button>' +
      '</form></div>';
  }

  function onChange(e) {
    if (e.target.id !== 'up-file' || !e.target.files[0]) return;
    file = e.target.files[0];
    if (!/^video\//.test(file.type)) { root.querySelector('[data-err]').textContent = 'Ce fichier n’est pas une vidéo. Choisis un fichier MP4 ou MOV.'; file = null; return; }
    if (file.size > 300 * 1024 * 1024) { root.querySelector('[data-err]').textContent = 'Vidéo trop lourde (300 Mo maximum).'; file = null; return; }
    root.querySelector('[data-err]').textContent = '';
    var drop = root.querySelector('[data-drop]'), url = URL.createObjectURL(file);
    drop.classList.add('has');
    drop.innerHTML = '<video src="' + url + '" controls playsinline muted></video>';
    var v = drop.querySelector('video'); v.addEventListener('loadedmetadata', function () { duration = Math.round(v.duration || 0); });
    var change = document.createElement('button'); change.type = 'button'; change.className = 'btn btn-ghost btn-sm'; change.textContent = 'Changer de vidéo'; change.setAttribute('data-reset', '');
    drop.after(change);
  }

  Frikip.views.publier = {
    mount: function (el) {
      root = el; render();
      el.addEventListener('change', onChange);
      el.addEventListener('input', function (e) { if (e.target.id === 'up-caption') el.querySelector('[data-left]').textContent = (300 - e.target.value.length) + ' caractères restants'; });
      el.addEventListener('click', function (e) {
        var t = e.target.closest('[data-addtag]');
        if (t) { var ta = el.querySelector('#up-caption'); ta.value = (ta.value.trim() + ' ' + t.getAttribute('data-addtag')).trim(); ta.dispatchEvent(new Event('input', { bubbles: true })); t.setAttribute('aria-pressed', 'true'); return; }
        if (e.target.closest('[data-reset]')) { render(); return; }
        if (e.target.closest('[data-back]')) { history.length > 1 ? history.back() : (location.hash = 'accueil'); }
      });
      el.addEventListener('submit', function (e) {
        e.preventDefault();
        var cap = el.querySelector('#up-caption').value.trim(), err = el.querySelector('[data-err]');
        if (!file) { err.textContent = 'Ajoute d’abord une vidéo.'; return; }
        if (!cap) { err.textContent = 'Écris une courte description pour présenter ta vidéo.'; el.querySelector('#up-caption').focus(); return; }
        var btn = el.querySelector('[data-submit]'); btn.disabled = true; btn.textContent = 'Publication…';
        api.publish({ file: file, caption: cap, visibility: el.querySelector('#up-vis').value, duration: duration || 30,
          onProgress: function (p) { btn.textContent = 'Envoi… ' + Math.round(p * 100) + ' %'; } }).then(function (res) {
          U.toast('Vidéo publiée');
          Frikip.state.feedTab = 'pour-toi';
          render();
          var id = res && (res.id || ((res.url || '').match(/[?&]v=([^&]+)/) || [])[1]);
          Frikip.views.accueil.reload();
          if (id) { Frikip.views.reels.focusVideo(decodeURIComponent(id)); location.hash = 'reels'; } else location.hash = 'accueil';
        }).catch(function (x) { btn.disabled = false; btn.textContent = 'Publier'; err.textContent = x.message; });
      });
    },
    show: function () {}
  };
})();
