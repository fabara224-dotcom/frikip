@extends('layouts.web')

@section('title', 'Recherche – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <h1>Recherche</h1>
  <form method="GET" action="{{ route('search') }}" class="card">
    <label for="q">Nom, poste, club, ville<input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="Ex. : ailier gaucher Conakry"></label>
    <div class="grid-form">
      @if (! \App\Terrain\SportFields::single())
      <label for="sport">Sport<select id="sport" name="sport">
        @foreach (array_merge(['Tous'], $sports) as $s)<option @selected($filters['sport'] === $s)>{{ $s }}</option>@endforeach
      </select></label>
      @endif
      <label for="poste">{{ __('Poste') }}<select id="poste" name="poste"><option value="">{{ __('Tous les postes') }}</option>
        @foreach ($postes as $p)<option value="{{ $p }}" @selected($filters['poste'] === $p)>{{ __($p) }}</option>@endforeach
      </select></label>
      <label for="pied">{{ __('Pied fort') }}<select id="pied" name="pied"><option value="">{{ __('Peu importe') }}</option>
        @foreach ($pieds as $p)<option value="{{ $p }}" @selected($filters['pied'] === $p)>{{ __($p) }}</option>@endforeach
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
      <span class="row" style="gap:14px;flex-wrap:wrap">
        <label class="check" for="verified"><input id="verified" name="verified" type="checkbox" value="1" @checked($filters['verified'])> Profils vérifiés seulement</label>
        <label class="check" for="dispo"><input id="dispo" name="dispo" type="checkbox" value="1" @checked($filters['dispo'])> {{ __('Disponibles pour un essai') }}</label>
      </span>
      <button class="btn" type="submit">Rechercher</button>
    </div>
  </form>

  @if (($me['role'] ?? '') === 'recruiter')
    <form method="POST" action="{{ route('alerts.store') }}" class="alertbar">@csrf
      @foreach (['q', 'sport', 'country', 'age', 'poste', 'pied'] as $k)<input type="hidden" name="{{ $k }}" value="{{ $filters[$k] }}">@endforeach
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
          @if (($me['role'] ?? '') === 'recruiter')<button class="btn ghost sm" type="button" data-shortlist="{{ $a['id'] }}">＋ {{ __('Liste') }}</button>@endif
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
  @if (($me['role'] ?? '') === 'recruiter')
    @include('web.partials.shortlist-sheet')
    <p style="margin-top:14px"><a href="{{ route('shortlists') }}">{{ __('Mes listes de joueurs') }}</a> · <a href="{{ route('contacts') }}">{{ __('Historique de mes contacts') }}</a></p>
  @endif
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
