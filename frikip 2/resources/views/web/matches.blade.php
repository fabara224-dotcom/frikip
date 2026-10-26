@extends('layouts.web')

@section('title', 'Matchs en direct – '.config('terrain.app_name'))
@section('description', 'Scores en direct, résultats et classements : Ligue des champions, Premier League, Liga, Serie A, Bundesliga, Ligue 1, Ligue 1 Pro de Guinée.')

@php
    $q = fn ($tab) => $tab === 'jour' ? [] : ['onglet' => $tab];
@endphp

@section('content')
<div class="page gut">
  <div class="livehead"><h1>Matchs</h1><a class="btn live sm" href="{{ route('live') }}">● {{ __('Regarder en direct') }}</a></div>
  <nav class="chips comptabs" aria-label="Compétitions">
    <a class="chip" href="{{ route('matches') }}" aria-current="{{ $tab === 'jour' ? 'true' : 'false' }}">Aujourd'hui</a>
    @foreach ($comps as $c)
      <a class="chip" href="{{ route('matches', $q((string) $c['id'])) }}" aria-current="{{ $tab === (string) $c['id'] ? 'true' : 'false' }}">
        @if ($c['logo'])<img src="{{ $c['logo'] }}" alt="" width="18" height="18" loading="lazy" onerror="this.remove()">@endif{{ $c['name'] }}</a>
    @endforeach
    <a class="chip" href="{{ route('matches', ['onglet' => 'local']) }}" aria-current="{{ $tab === 'local' ? 'true' : 'false' }}">{{ $demo ? 'Démo · autres sports' : 'Matchs locaux' }}</a>
  </nav>
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
  })();
</script>
@endpush
