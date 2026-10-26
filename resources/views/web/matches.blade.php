@extends('layouts.web')

@section('title', 'Matchs en direct – '.config('terrain.app_name'))
@section('description', 'Scores en direct, résultats et classements : Ligue des champions, Premier League, Liga, Serie A, Bundesliga, Ligue 1, Ligue 1 Pro de Guinée.')

@php
    $q = fn ($tab) => $tab === 'jour' ? [] : ['onglet' => $tab];
@endphp

@section('content')
<div class="page gut">
  <div class="livehead"><h1>Matchs</h1><a class="btn live sm" href="{{ app(\App\Terrain\Growth\PartnerLink::class)->get() ? route('live.partner') : route('live') }}">● {{ __('Regarder en direct') }}</a></div>
  <nav class="chips comptabs" aria-label="Compétitions">
    <a class="chip" href="{{ route('matches') }}" aria-current="{{ $tab === 'jour' ? 'true' : 'false' }}">{{ __('Tous les matchs') }}</a>
    @foreach ($comps as $c)
      <a class="chip" href="{{ route('matches', $q((string) $c['id'])) }}" aria-current="{{ $tab === (string) $c['id'] ? 'true' : 'false' }}">
        @if ($c['logo'])<img src="{{ $c['logo'] }}" alt="" width="18" height="18" loading="lazy" onerror="this.remove()">@endif{{ $c['name'] }}</a>
    @endforeach
    <a class="chip" href="{{ route('matches', ['onglet' => 'local']) }}" aria-current="{{ $tab === 'local' ? 'true' : 'false' }}">{{ $demo ? 'Démo · autres sports' : 'Matchs locaux' }}</a>
  </nav>
  @if ($tab === 'jour' && $realEnabled)
    {{-- Bande des jours : on défile pour voir hier, demain et toute la semaine --}}
    <nav class="daystrip" aria-label="{{ __('Choisir le jour') }}">
      <button class="dsarrow" type="button" data-step="-1" aria-label="{{ __('Jours précédents') }}">‹</button>
      <div class="dsscroll" id="dsscroll">
        @foreach ($days as $d)
          <a class="dsday" href="{{ route('matches', $d['offset'] === 0 ? [] : ['jour' => $d['date']]) }}" data-day="{{ $d['date'] }}" data-offset="{{ $d['offset'] }}" @if ($d['date'] === $day) aria-current="date" @endif>
            <span>{{ $d['top'] }}</span><b>{{ $d['bottom'] }}</b>
          </a>
        @endforeach
      </div>
      <button class="dsarrow" type="button" data-step="1" aria-label="{{ __('Jours suivants') }}">›</button>
    </nav>
  @endif
  <div id="match-list" data-src="{{ route('matches.partial', request()->query()) }}" data-refresh="{{ $refresh }}">
    @include('web.partials.match-list')
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Mise à jour automatique : toutes les 15 s pendant un match en direct, sinon toutes les 60 s.
  (function () {
    var box = document.getElementById('match-list');
    function tick() {
      if (!document.hidden) {
        fetch(box.dataset.src).then(function (r) { return r.ok ? r.text() : null; }).then(function (html) {
          if (!html) return;
          box.innerHTML = html;
          var next = box.querySelector('[data-refresh]');
          if (next) box.dataset.refresh = next.dataset.refresh;
        }).catch(function () {});
      }
      setTimeout(tick, (parseInt(box.dataset.refresh, 10) || 30) * 1000);
    }
    setTimeout(tick, (parseInt(box.dataset.refresh, 10) || 30) * 1000);

    // Bande des jours : le jour choisi au centre, flèches, clic sans recharger la page, glisser sur la liste
    var strip = document.getElementById('dsscroll');
    if (!strip) return;
    var partial = @json(route('matches.partial')), page = @json(route('matches'));
    function centre(smooth) {
      var cur = strip.querySelector('[aria-current="date"]');
      if (cur) strip.scrollTo({ left: cur.offsetLeft - (strip.clientWidth - cur.offsetWidth) / 2, behavior: smooth ? 'smooth' : 'auto' });
    }
    function show(a) {
      if (!a) return;
      strip.querySelectorAll('.dsday').forEach(function (x) { x.removeAttribute('aria-current'); });
      a.setAttribute('aria-current', 'date'); centre(true);
      var q = a.dataset.offset === '0' ? '' : '?jour=' + a.dataset.day;
      box.dataset.src = partial + q; box.style.opacity = '.45';
      history.replaceState(null, '', page + q);
      fetch(box.dataset.src).then(function (r) { return r.ok ? r.text() : null; }).then(function (html) {
        box.style.opacity = ''; if (!html) { location.href = page + q; return; }
        box.innerHTML = html; var next = box.querySelector('[data-refresh]'); if (next) box.dataset.refresh = next.dataset.refresh;
      }).catch(function () { location.href = page + q; });
    }
    strip.addEventListener('click', function (e) {
      var a = e.target.closest('.dsday'); if (!a || e.metaKey || e.ctrlKey) return;
      e.preventDefault(); show(a);
    });
    function step(n) {
      var cur = strip.querySelector('[aria-current="date"]'), all = Array.prototype.slice.call(strip.querySelectorAll('.dsday'));
      show(all[Math.max(0, Math.min(all.length - 1, all.indexOf(cur) + n))]);
    }
    document.querySelectorAll('.dsarrow').forEach(function (b) { b.addEventListener('click', function () { step(parseInt(b.dataset.step, 10)); }); });
    var sx = null, sy = null;
    box.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
    box.addEventListener('touchend', function (e) {
      if (sx === null) return; var dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy; sx = null;
      if (Math.abs(dx) > 70 && Math.abs(dy) < 50 && !e.target.closest('.standings, table, .chips')) step(dx < 0 ? 1 : -1);
    }, { passive: true });
    centre(false);
  })();
</script>
@endpush
