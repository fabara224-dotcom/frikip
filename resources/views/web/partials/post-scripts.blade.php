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
  var rm = document.getElementById('post-photo-rm');
  if (rm) rm.addEventListener('click', function () { input.value = ''; prev.hidden = true; });
})();
</script>
