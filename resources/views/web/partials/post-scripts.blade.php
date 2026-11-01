{{-- J'aime sans recharger la page, partage, aperçu de la photo choisie. --}}
<script>
(function () {
  document.addEventListener('click', function (e) {
    var like = e.target.closest('[data-like]');
    if (like) {
      if (!window.Terrain.loggedIn) { location.href = window.Terrain.loginUrl; return; }
      like.disabled = true;
      window.Terrain.post(like.dataset.like).then(function (d) {
        like.classList.toggle('on', d.liked); like.setAttribute('aria-pressed', d.liked ? 'true' : 'false');
        var n = like.closest('.post').querySelector('[data-likes]'); if (n) n.textContent = d.likes;
      }).catch(function () { window.Terrain.toast(@json(__('Action impossible, réessaie.'))); }).finally(function () { like.disabled = false; });
      return;
    }
    var share = e.target.closest('[data-share]');
    if (share) {
      var url = share.dataset.share;
      if (navigator.share) { navigator.share({ url: url }).catch(function () {}); return; }
      (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () { window.Terrain.toast(@json(__('Lien copié'))); }, function () { prompt('', url); });
    }
  });
  var input = document.getElementById('post-photo'), prev = document.getElementById('post-preview');
  if (input && prev) input.addEventListener('change', function () {
    var f = input.files && input.files[0];
    prev.hidden = !f; if (f) { prev.querySelector('img').src = URL.createObjectURL(f); }
  });
  // Photo lourde : réduite à 1600 px dans le navigateur avant l'envoi (3G, limite d'envoi du serveur)
  var comp = document.querySelector('form.composer');
  if (comp && input) comp.addEventListener('submit', function (e) {
    var f = input.files && input.files[0];
    if (!f || f.size < 900 * 1024 || !window.createImageBitmap || !window.fetch) return;
    e.preventDefault();
    var btn = comp.querySelector('button[type=submit]'); btn.disabled = true; btn.textContent = @json(__('Envoi…'));
    createImageBitmap(f).then(function (bmp) {
      var k = Math.min(1, 1600 / Math.max(bmp.width, bmp.height)), c = document.createElement('canvas');
      c.width = Math.round(bmp.width * k); c.height = Math.round(bmp.height * k);
      c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
      return new Promise(function (ok) { c.toBlob(ok, 'image/jpeg', 0.85); });
    }).then(function (blob) {
      var fd = new FormData(comp); fd.delete('photo'); fd.append('photo', blob || f, 'photo.jpg');
      return fetch(comp.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.Terrain.csrf } });
    }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) {
      if (r.ok && d.redirect) { location.href = d.redirect; return; }
      btn.disabled = false; btn.textContent = @json(__('Publier'));
      window.Terrain.toast((d.errors ? Object.values(d.errors)[0][0] : d.message) || @json(__('Envoi impossible. Réessaie.')));
    }); }, function () { comp.submit(); });
  });
  var rm = document.getElementById('post-photo-rm');
  if (rm) rm.addEventListener('click', function () { input.value = ''; prev.hidden = true; });
})();
</script>
