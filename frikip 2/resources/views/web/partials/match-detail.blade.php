@php
    $isRecruiter = ($me['role'] ?? null) === 'recruiter';
    $isReal = ($m['source'] ?? null) === 'api';
    $canOrganize = ! $isReal && $isRecruiter && ($demo || ! empty($m['organizer'])) && $m['result'] === null && $m['status'] !== 'finished';
    $total = array_sum($m['votes']);
@endphp
<div class="cols" style="margin-top:12px">
  <div>
    <div class="board">
      <div class="meta">{{ $m['sport'] }} · {{ $m['competition'] }}</div>
      @if ($m['result'] === null)
        <div class="teams">
          <div class="tn">@if (! empty($m['home_logo']))<img class="clublogo" src="{{ $m['home_logo'] }}" alt="" width="64" height="64" onerror="this.remove()">@else<x-av :name="$m['home']" :sport="$m['sport']" lg />@endif{{ $m['home'] }}</div>
          <div class="big">{{ $m['status'] === 'upcoming' ? '– : –' : $m['hs'].' : '.$m['as'] }}</div>
          <div class="tn">@if (! empty($m['away_logo']))<img class="clublogo" src="{{ $m['away_logo'] }}" alt="" width="64" height="64" onerror="this.remove()">@else<x-av :name="$m['away']" sport="Judo" lg />@endif{{ $m['away'] }}</div>
        </div>
      @else
        <h1 style="margin:0">{{ $m['home'] }}<br><span style="font-size:.6em;color:var(--muted)">{{ $m['away'] }}</span></h1>
        <p style="margin:0;font-weight:700">{{ $m['result'] }}</p>
      @endif
      @include('web.partials.match-status', ['m' => $m])
      @if ($isReal)<span class="pill ok">Données réelles · API-Football</span><a class="meta" href="{{ route('matches', ['onglet' => $m['league_id']]) }}">{{ $m['competition'] }}</a>@endif
      @if (! empty($m['venue']))<span class="meta">{{ $m['venue'] }}@if (! empty($m['referee'])) · Arbitre : {{ $m['referee'] }}@endif</span>@endif
    </div>

    @if (! empty($m['detail_note']))
      <p class="note" style="margin-top:16px">{{ $m['detail_note'] }}</p>
    @endif

    @if ($m['sport'] === 'Football' && (count($m['events']) || empty($m['detail_note'])))
      <div class="section-label">Déroulé du match</div>
      @if (! count($m['events']))<p class="meta">{{ $m['status'] === 'upcoming' ? "Le match n'a pas encore commencé." : 'Le déroulé détaillé n\'est pas fourni pour ce match.' }}</p>@endif
      <div class="tl">
        @foreach (array_reverse($m['events']) as $e)
          @php
            $icon = $e['type'] === 'but' ? '<b>But</b>' : ($e['type'] === 'jaune' ? '<i class="card-y"></i>' : '<i class="card-r"></i>');
            $who = $e['athlete_id'] ? '<a href="'.e(route('athletes.show', $e['athlete_id'])).'">'.e($e['player']).'</a>' : e($e['player']);
          @endphp
          <div class="ev">
            <div class="l">@if ($e['side'] === 'h'){!! $who !!} {!! $icon !!}@endif</div>
            <div class="mn">{{ $e['label'] ?? $e['minute'] }}'</div>
            <div>@if ($e['side'] === 'a'){!! $icon !!} {!! $who !!}@endif</div>
          </div>
        @endforeach
      </div>
    @endif

    @if (! empty($m['lineups']))
      <div class="section-label">Compositions</div>
      <div class="grid2" style="gap:12px">
        @foreach ($m['lineups'] as $l)
          <div class="card" style="gap:4px">
            <b>{{ $l['team'] }} @if ($l['formation'])<span class="meta">· {{ $l['formation'] }}</span>@endif</b>
            @foreach ($l['players'] as $p)<span style="font-size:14.5px">{{ $p }}</span>@endforeach
            @if ($l['coach'])<span class="meta">Entraîneur : {{ $l['coach'] }}</span>@endif
          </div>
        @endforeach
      </div>
    @endif

    @if (count($m['stats']) && $m['status'] !== 'upcoming')
      <div class="section-label">Statistiques</div>
      @foreach ($m['stats'] as $label => $pair)
        @php($t = max(1, $pair[0] + $pair[1]))
        <div class="sbar"><span class="lab">{{ $label }}</span><span class="v">{{ $pair[0] }}</span>
          <div class="tr"><i style="width:{{ $pair[0] / $t * 100 }}%"></i><i style="width:{{ $pair[1] / $t * 100 }}%"></i></div><span class="v">{{ $pair[1] }}</span></div>
      @endforeach
    @endif
  </div>

  <div>
    @if (count($m['ratings']))
      <div class="section-label" style="margin-top:0">Talents {{ config('terrain.app_name') }} dans ce match</div>
      <div class="list">
        @foreach ($m['ratings'] as $r)
          @if ($a = $athletes[$r['athlete_id']] ?? null)
            @php($n = $r['note'])
            <a class="li" href="{{ route('athletes.show', $a['id']) }}">
              <span class="note10" style="background:{{ $n >= 8.5 ? 'var(--pitch)' : ($n >= 7 ? 'var(--accent)' : 'var(--gold)') }}">{{ str_replace('.', ',', number_format($n, 1)) }}</span>
              <span class="grow"><span class="t">{{ $a['name'] }} @if (! empty($a['star']))<x-star />@endif</span><span class="s">{{ $r['detail'] }}</span></span>
            </a>
          @endif
        @endforeach
      </div>
      <p class="meta">Note sur 10 : organisateur, scouts vérifiés et vote du public.</p>
    @endif

    @if (count($m['candidates']) && $m['status'] !== 'upcoming')
      <div class="section-label">Joueur du match · vote du public</div>
      @if ($m['my_vote'] === null)
        @if ($me)
          @foreach ($m['candidates'] as $i => $c)
            <form method="POST" action="{{ route('matches.vote', $m['id']) }}" style="margin-bottom:6px">@csrf<input type="hidden" name="candidate" value="{{ $i }}"><button class="btn ghost" style="width:100%;justify-content:flex-start" type="submit">{{ $c }}</button></form>
          @endforeach
        @else
          <p><a href="{{ route('login') }}">Connecte-toi</a> pour voter.</p>
        @endif
      @else
        @foreach ($m['candidates'] as $i => $c)
          @php($pct = $total ? round($m['votes'][$i] / $total * 100) : 0)
          <div style="margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;font-weight:{{ $m['my_vote'] === $i ? 700 : 500 }}"><span>{{ $c }}{{ $m['my_vote'] === $i ? ' · ton vote' : '' }}</span><span>{{ $pct }} %</span></div>
            <div class="meter"><i style="width:{{ $pct }}%"></i></div>
          </div>
        @endforeach
      @endif
    @endif

    @if ($canOrganize)
      <div class="section-label">Saisie du score · organisateur</div>
      <div class="card">
        <p class="meta" style="margin:0">Chaque saisie est envoyée en direct à tous les abonnés du match.@if ($demo) En démo, tout recruteur peut essayer.@endif</p>
        @error('organizer')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
        @if ($m['status'] === 'upcoming')
          <form method="POST" action="{{ route('matches.organizer', $m['id']) }}">@csrf<input type="hidden" name="action" value="start"><button class="btn" type="submit">Lancer le match</button></form>
        @else
          <div class="grid2" style="gap:8px">
            @foreach (['h' => $m['home'], 'a' => $m['away']] as $side => $team)
              <div style="display:flex;flex-direction:column;gap:6px">
                <b style="font-size:14px">{{ $team }}</b>
                @foreach ($m['sport'] === 'Basket' ? [1, 2, 3] : [1] as $p)
                  <form method="POST" action="{{ route('matches.organizer', $m['id']) }}">@csrf<input type="hidden" name="action" value="score"><input type="hidden" name="side" value="{{ $side }}"><input type="hidden" name="points" value="{{ $p }}"><button class="btn ghost" style="width:100%" type="submit">+{{ $p }}</button></form>
                @endforeach
              </div>
            @endforeach
          </div>
          <form method="POST" action="{{ route('matches.organizer', $m['id']) }}">@csrf<input type="hidden" name="action" value="end"><button class="btn" type="submit">Siffler la fin du match</button></form>
        @endif
      </div>
    @endif
  </div>
</div>
