<span hidden data-refresh="{{ $refresh }}"></span>
@if ($realError)
  <p class="note">Le fournisseur de scores répond : « {{ $realError }} ». Les derniers scores reçus restent affichés.</p>
@endif

{{-- ====== Matchs du jour, toutes compétitions ====== --}}
@if ($tab === 'jour' && $realEnabled)
  @forelse ($today as $g)
    <section class="compblock">
      <a class="comphead" href="{{ route('matches', ['onglet' => $g['comp']['id']]) }}">
        @if (! empty($g['comp']['logo']))<img src="{{ $g['comp']['logo'] }}" alt="" width="26" height="26" loading="lazy" onerror="this.remove()">@endif
        <h2>{{ $g['comp']['name'] }}</h2><span class="meta">Classement et calendrier ›</span>
      </a>
      <div class="mgrid">
        @foreach ($g['matches'] as $m)
          @include('web.partials.match-card', ['m' => $m])
        @endforeach
      </div>
    </section>
  @empty
    <p class="meta" style="padding:20px 0">Aucun match aujourd'hui dans tes compétitions. Choisis une compétition pour voir ses derniers résultats et son calendrier.</p>
  @endforelse
  <p class="meta">Scores fournis par API-Football.</p>
@endif

{{-- ====== Sans clé : vraie fin de saison de la Ligue 1 Pro guinéenne ====== --}}
@if ($tab === 'jour' && ! $realEnabled)
  <section class="compblock">
    <div class="comphead"><h2>Ligue 1 Pro (Guinée)</h2><span class="pill ok">Données réelles</span></div>
    <div class="section-label">Classement final · saison 2025-26</div>
    @include('web.partials.standings-table', ['rows' => $lastSeason])
    <p class="meta">Source : <a href="{{ $lastSeasonSource }}" target="_blank" rel="noopener">matchendirect.fr</a>, consulté le 20 octobre 2026.
      Pour les scores en direct de la Ligue des champions, de la Premier League, de la Liga, de la Serie A et des autres compétitions, ajoute une clé API-Football (voir README).</p>
  </section>
@endif

{{-- ====== Une compétition ====== --}}
@if ($league)
  <section class="compblock">
    <div class="comphead">
      @if ($league['competition']['logo'])<img src="{{ $league['competition']['logo'] }}" alt="" width="32" height="32" loading="lazy" onerror="this.remove()">@endif
      <h2>{{ $league['competition']['name'] }}</h2>
      <span class="meta">Saison {{ $league['competition']['season'] }}-{{ substr((string) ($league['competition']['season'] + 1), -2) }}</span>
    </div>
    @foreach (['En direct' => $league['live'], 'Derniers résultats' => $league['results'], 'Prochains matchs' => $league['next']] as $title => $list)
      @if (count($list))
        <div class="section-label">{{ $title }}</div>
        <div class="mgrid">
          @foreach ($list as $m)
            @include('web.partials.match-card', ['m' => $m])
          @endforeach
        </div>
      @endif
    @endforeach
    @if (! count($league['live']) && ! count($league['results']) && ! count($league['next']))
      <p class="meta">Pas encore de matchs publiés pour cette saison.</p>
    @endif
    @foreach ($league['standings'] as $group)
      <div class="section-label">{{ $group['title'] ?: 'Classement' }}</div>
      @include('web.partials.standings-table', ['rows' => $group['rows']])
    @endforeach
  </section>
@elseif ($tab !== 'jour' && $tab !== 'local')
  <p class="meta">Compétition indisponible pour le moment.</p>
@endif

{{-- ====== Matchs locaux / démo ====== --}}
@if ($tab === 'local' || ! $realEnabled)
  <section class="compblock">
    <div class="comphead"><h2>{{ $demo ? 'Matchs de démonstration' : 'Matchs locaux' }}</h2></div>
    <p class="meta" style="margin-top:0">{{ $demo ? 'Scores fictifs : ces matchs avancent tout seuls et recommencent en boucle.' : 'Matchs saisis en direct par les organisateurs et les clubs sur Terrain.' }}</p>
    @foreach (['En direct' => $live, 'Résultats' => $done, 'À venir' => $next] as $title => $list)
      @if (count($list))
        <div class="section-label">{{ $title }}</div>
        <div class="mgrid">
          @foreach ($list as $m)
            @include('web.partials.match-card', ['m' => $m])
          @endforeach
        </div>
      @endif
    @endforeach
    @if (! count($live) && ! count($done) && ! count($next))
      <p class="meta" style="padding:20px 0">Aucun match pour ce sport cette semaine.</p>
    @endif
    @if (count($standings))
      <div class="section-label">Classement · Championnat national U20{{ $demo ? ' (démo)' : '' }}</div>
      @include('web.partials.standings-table', ['rows' => $standings])
    @endif
  </section>
@endif
