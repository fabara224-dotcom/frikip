<script>
// Inscription : affiche seulement les questions du type de compte choisi, et l'étape en cours
(function () {
  var form = document.getElementById('su'); if (!form) return;
  function type() { var t = form.querySelector('[name=type]:checked'); return t ? t.value : ''; }
  function sync() {
    var t = type();
    form.querySelectorAll('[data-types]').forEach(function (el) {
      var on = (' ' + el.dataset.types + ' ').indexOf(' ' + t + ' ') !== -1;
      el.hidden = !on;
      el.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !on; });
    });
    form.querySelectorAll('[data-label]').forEach(function (el) {
      el.hidden = el.dataset.label !== t && !(t === '' && el.dataset.label === 'joueur');
    });
    var age = form.querySelector('[data-age]');
    var minor = age && !age.disabled && parseInt(age.value, 10) > 0 && parseInt(age.value, 10) < 18;
    var g = document.getElementById('guardian');
    if (g) { g.hidden = !minor; var gp = document.getElementById('guardian_phone'); gp.disabled = !minor; gp.required = minor; }
    // liens Google / Facebook / TikTok / téléphone : on garde le type choisi
    form.querySelectorAll('[data-auth]').forEach(function (a) {
      var u = new URL(a.href, location.href); if (t) u.searchParams.set('type', t); else u.searchParams.delete('type'); a.href = u.toString();
    });
    var next = form.querySelector('[data-goto="2"]'); if (next) next.disabled = !t;
  }
  function show(n, quiet) {
    form.querySelectorAll('[data-step]').forEach(function (s) { s.hidden = s.dataset.step !== String(n); });
    document.querySelectorAll('.steps li').forEach(function (li, i) { li.classList.toggle('on', i + 1 === n); li.classList.toggle('done', i + 1 < n); });
    if (quiet) return;
    var h = form.querySelector('[data-step="' + n + '"] h2'); if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  form.addEventListener('change', sync); form.addEventListener('input', sync);
  form.querySelectorAll('[data-goto]').forEach(function (b) { b.addEventListener('click', function () { show(parseInt(b.dataset.goto, 10)); }); });
  // une carte choisie à l'étape 1 : on passe tout seul à l'étape 2
  form.querySelectorAll('[name=type]').forEach(function (r) { r.addEventListener('change', function () { if (form.querySelector('[data-step="2"]')) setTimeout(function () { show(2); }, 180); }); });
  sync();
  show(parseInt(form.dataset.start || '1', 10), true);
})();
</script>
