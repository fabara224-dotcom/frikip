{{-- Compte à rebours des défis (mis à jour chaque minute). --}}
<script>
(function () {
  function tick() {
    document.querySelectorAll('.countdown[data-end]').forEach(function (el) {
      var ms = Math.max(0, new Date(el.dataset.end) - new Date()), m = Math.floor(ms / 60000);
      var b = el.querySelectorAll('b'); b[0].textContent = Math.floor(m / 1440); b[1].textContent = Math.floor(m % 1440 / 60); b[2].textContent = m % 60;
    });
  }
  tick(); setInterval(tick, 30000);
})();
</script>
