{{-- Fenêtre « Ajouter à une liste » (recruteurs) : ouverte par un bouton [data-shortlist="<id du joueur>"]. --}}
@php
  $lists = app(\App\Terrain\Growth\Shortlists::class)->of($me['id']);
@endphp
<div class="sheet-ov" id="slsheet" hidden>
  <div class="sheet" role="dialog" aria-labelledby="sl-title">
    <div class="hd"><b id="sl-title">{{ __('Ajouter à une liste') }}</b><button class="x" type="button" data-close aria-label="{{ __('Fermer') }}">×</button></div>
    <div class="bd" id="sl-lists">
      @forelse ($lists as $l)<button class="listrow sl-pick" type="button" data-list="{{ $l->id }}" data-url="{{ route('shortlists.toggle', $l->id) }}"><span><b>{{ $l->name }}</b><small>{{ trans_choice(':n joueur|:n joueurs', $l->count, ['n' => $l->count]) }}</small></span><span class="pill">＋</span></button>@empty<p class="meta" style="margin:0">{{ __('Crée ta première liste :') }}</p>@endforelse
    </div>
    <form id="sl-new"><input name="name" maxlength="80" required placeholder="{{ __('Nouvelle liste, ex. : Attaquants U20') }}" aria-label="{{ __('Nouvelle liste') }}"><button class="btn" type="submit">{{ __('Créer') }}</button></form>
  </div>
</div>
@push('scripts')
<script>
(function () {
  var sheet = document.getElementById('slsheet'), athlete = null, T = window.Terrain;
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-shortlist]'); if (b) { athlete = b.dataset.shortlist; sheet.hidden = false; return; }
    if (e.target === sheet || e.target.closest('#slsheet [data-close]')) { sheet.hidden = true; return; }
    var p = e.target.closest('.sl-pick'); if (p && athlete) {
      fetch(p.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': T.csrf, Accept: 'application/json' }, body: JSON.stringify({ athlete: athlete }) })
        .then(function (r) { return r.json(); }).then(function (d) { T.toast(d.message || ''); sheet.hidden = true; });
    }
  });
  document.getElementById('sl-new').addEventListener('submit', function (e) {
    e.preventDefault(); var name = this.name.value.trim(); if (!name || !athlete) return;
    fetch(@json(route('shortlists.store')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': T.csrf, Accept: 'application/json' }, body: JSON.stringify({ name: name, athlete: athlete }) })
      .then(function (r) { return r.json(); }).then(function (d) { if (d.ok) { T.toast(@json(__('Liste créée, joueur ajouté.'))); location.reload(); } });
  });
})();
</script>
@endpush
