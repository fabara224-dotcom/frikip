@extends('layouts.web')

@section('title', $m['home'].($m['away'] ? ' – '.$m['away'] : '').' · '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <a href="{{ ($m['source'] ?? null) === 'api' ? route('matches', ['onglet' => $m['league_id']]) : route('matches') }}">← Retour aux matchs</a>
  @php
    $myPick = app(\App\Terrain\Growth\ScoreChoice::class)->get();
    $pinned = $myPick['mode'] === 'match' && $myPick['value'] === (string) $m['id'];
  @endphp
  <form method="POST" action="{{ route('score.choose') }}" class="mypick">@csrf
    <input type="hidden" name="back" value="{{ route('matches.show', $m['id']) }}">
    @if ($pinned)
      <input type="hidden" name="mode" value="auto">
      <button class="btn ghost sm" type="submit">✓ {{ __('Affiché dans mon fil') }} · {{ __('Retirer') }}</button>
    @else
      <input type="hidden" name="mode" value="match"><input type="hidden" name="value" value="{{ $m['id'] }}">
      <input type="hidden" name="label" value="{{ $m['home'] }}{{ $m['away'] ? ' – '.$m['away'] : '' }}">
      <button class="btn ghost sm" type="submit">{{ __('Afficher dans mon fil') }}</button>
    @endif
  </form>
  @include('web.partials.match-watch')
  <div id="match-detail" data-src="{{ route('matches.show.partial', $m['id']) }}" data-live="{{ $m['status'] === 'live' ? '1' : '0' }}">
    @include('web.partials.match-detail')
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    var box = document.getElementById('match-detail');
    if (box.dataset.live !== '1') return;
    setInterval(function () { // toutes les 15 secondes pendant le match
      if (document.hidden || box.contains(document.activeElement) && document.activeElement.tagName !== 'BODY') return;
      fetch(box.dataset.src).then(function (r) { return r.ok ? r.text() : null; }).then(function (html) { if (html) box.innerHTML = html; }).catch(function () {});
    }, 15000);
  })();
</script>
@endpush
