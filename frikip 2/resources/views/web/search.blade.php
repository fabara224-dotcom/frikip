@extends('layouts.web')

@section('title', 'Recherche – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <h1>Recherche</h1>
  <form method="GET" action="{{ route('search') }}" class="card">
    <label for="q">Nom, poste, club, ville<input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="Ex. : ailier gaucher Conakry"></label>
    <div class="grid-form">
      <label for="sport">Sport<select id="sport" name="sport">
        @foreach (array_merge(['Tous'], $sports) as $s)<option @selected($filters['sport'] === $s)>{{ $s }}</option>@endforeach
      </select></label>
      <label for="country">Pays<select id="country" name="country">
        @foreach (array_merge(['Tous'], $countries) as $c)<option @selected($filters['country'] === $c)>{{ $c }}</option>@endforeach
      </select></label>
      <label for="age">Âge<select id="age" name="age">
        @foreach (['Tous' => 'Tous', '-18' => 'Moins de 18 ans', '18-21' => '18 à 21 ans', '22+' => '22 ans et plus'] as $k => $l)<option value="{{ $k }}" @selected($filters['age'] === $k)>{{ $l }}</option>@endforeach
      </select></label>
      <label for="sort">Trier par<select id="sort" name="sort">
        @foreach (['score' => 'Pertinence', 'vues' => 'Plus vus', 'jeune' => 'Plus jeunes'] as $k => $l)<option value="{{ $k }}" @selected($filters['sort'] === $k)>{{ $l }}</option>@endforeach
      </select></label>
    </div>
    <div class="row" style="justify-content:space-between">
      <label class="check" for="verified"><input id="verified" name="verified" type="checkbox" value="1" @checked($filters['verified'])> Profils vérifiés seulement</label>
      <button class="btn" type="submit">Rechercher</button>
    </div>
  </form>

  @if (($me['role'] ?? '') === 'recruiter')
    <form method="POST" action="{{ route('alerts.store') }}" class="alertbar">@csrf
      @foreach (['q', 'sport', 'country', 'age'] as $k)<input type="hidden" name="{{ $k }}" value="{{ $filters[$k] }}">@endforeach
      @if ($filters['verified'])<input type="hidden" name="verified" value="1">@endif
      <span>📣 Être prévenu des <b>nouveaux</b> profils pour cette recherche</span>
      <button class="btn ghost sm" type="submit">Créer une alerte</button>
    </form>
  @endif
  <p class="meta">{{ $total }} profil{{ $total > 1 ? 's' : '' }}@if ($pages > 1) · page {{ $page }} sur {{ $pages }}@endif · coche deux profils pour les comparer</p>
  <form method="GET" action="{{ route('compare') }}" id="cmp-form">
    <input type="hidden" name="ids" id="cmp-ids">
    <div class="list">
      @forelse ($results as $a)
        <div class="li">
          <x-av :name="$a['name']" :sport="$a['sport']" />
          <a class="grow" href="{{ route('athletes.show', $a['id']) }}" style="color:inherit;text-decoration:none">
            <span class="t">{{ $a['name'] }} @if (! empty($a['star']))<x-star />@endif</span>
            <span class="s">{{ $a['sport'] }} · {{ \Illuminate\Support\Arr::first($a['fields']) }} · {{ $a['age'] }} ans · {{ $a['city'] }}, {{ $a['country'] }}</span>
            @if (! empty($a['specialty']))<span class="s">★ {{ $a['specialty'] }}@if (! empty($a['demo'])) · <i>profil fictif</i>@endif</span>@endif
          </a>
          @if ($a['age'] < 18)<span class="pill warn">Mineur</span>@endif
          <label class="check" style="font-size:14px"><input type="checkbox" class="cmp" value="{{ $a['id'] }}"> Comparer</label>
        </div>
      @empty
        <p class="meta" style="padding:24px 0">Aucun profil ne correspond. Élargis un filtre.</p>
      @endforelse
    </div>
    @if ($pages > 1)
      <nav class="pager" aria-label="Pages de résultats">
        @if ($page > 1)<a class="btn ghost" href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">← Précédents</a>@endif
        <span class="meta">{{ $page }} / {{ $pages }}</span>
        @if ($page < $pages)<a class="btn ghost" href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Suivants →</a>@endif
      </nav>
    @endif
    <div class="compose" style="justify-content:flex-end"><button class="btn" id="cmp-btn" type="submit" disabled>Comparer (0/2)</button></div>
  </form>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    var boxes = Array.prototype.slice.call(document.querySelectorAll('.cmp')), btn = document.getElementById('cmp-btn'), picked = [];
    boxes.forEach(function (b) {
      b.addEventListener('change', function () {
        if (b.checked) { picked.push(b.value); if (picked.length > 2) { var out = picked.shift(); boxes.forEach(function (x) { if (x.value === out) x.checked = false; }); } }
        else picked = picked.filter(function (v) { return v !== b.value; });
        btn.disabled = picked.length !== 2; btn.textContent = 'Comparer (' + picked.length + '/2)';
        document.getElementById('cmp-ids').value = picked.join(',');
      });
    });
  })();
</script>
@endpush
