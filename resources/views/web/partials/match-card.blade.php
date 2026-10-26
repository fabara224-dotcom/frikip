@php
    $showScore = $m['result'] === null && $m['status'] !== 'upcoming';
    $decided = $showScore && $m['status'] === 'finished' && $m['hs'] !== $m['as'];
    $logo = fn ($src) => $src ? '<img src="'.e($src).'" alt="" width="20" height="20" loading="lazy" onerror="this.remove()">' : '';
@endphp
<a class="mcard {{ $m['status'] === 'live' ? 'is-live' : '' }}" href="{{ route('matches.show', $m['id']) }}">
  <div class="cp"><span>{{ $m['competition'] }}</span>@include('web.partials.match-status', ['m' => $m])</div>
  @if ($m['result'] === null)
    <div class="tm {{ $decided && $m['hs'] < $m['as'] ? 'lose' : '' }}"><span class="team">{!! $logo($m['home_logo'] ?? '') !!}{{ $m['home'] }}</span><b>{{ $showScore ? $m['hs'] : '–' }}</b></div>
    <div class="tm {{ $decided && $m['as'] < $m['hs'] ? 'lose' : '' }}"><span class="team">{!! $logo($m['away_logo'] ?? '') !!}{{ $m['away'] }}</span><b>{{ $showScore ? $m['as'] : '–' }}</b></div>
  @else
    <div class="tm"><span>{{ $m['home'] }} · {{ $m['away'] }}</span></div>
    <div class="s" style="font-size:14px;color:var(--muted)">{{ $m['result'] }}</div>
  @endif
</a>
