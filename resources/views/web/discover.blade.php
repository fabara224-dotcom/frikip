@extends('layouts.web')

@section('title', __('Découvrir').' – '.config('terrain.app_name'))
@section('description', __('Exercices, gestes techniques, gardiens, tactique, arbitrage, santé : des vidéos de football pour apprendre et progresser.'))

@section('content')
<div class="page gut">
  <p class="meta" style="margin:0"><a href="{{ route('explore') }}">← {{ __('Explorer') }}</a></p>
  <h1>{{ __('Découvrir') }}</h1>
  <p class="pub-lead">{{ __('Des vidéos de foot pour apprendre, t’entraîner et t’inspirer, choisies sur YouTube. Les vidéos appartiennent à leurs chaînes : elles sont lues avec le lecteur officiel de YouTube.') }}</p>

  @if ($topics)
    <nav class="chips pickchips exchips" aria-label="{{ __('Thème') }}">
      <a class="chip" href="{{ route('discover') }}" aria-current="{{ $topic ? 'false' : 'true' }}">{{ __('Tout') }} ({{ number_format($total, 0, ',', ' ') }})</a>
      @foreach ($topics as $t => $n)<a class="chip" href="{{ route('discover', ['theme' => $t]) }}" aria-current="{{ $topic === $t ? 'true' : 'false' }}">{{ __($t) }}</a>@endforeach
    </nav>
  @endif

  @if (count($list))
    <div class="dgrid" id="dgrid">@include('web.partials.discover-cards', ['list' => $list])</div>
    @if ($next)<div class="formbar" style="justify-content:center;margin-top:16px"><button class="btn ghost" type="button" id="dmore" data-next="{{ $next }}">{{ __('Voir plus de vidéos') }}</button></div>@endif
  @else
    <div class="empty-state">
      <p>{{ __('Les vidéos arrivent bientôt. Pendant ce temps, regarde les vidéos des joueurs dans l’Accueil.') }}</p>
      <a class="btn" href="{{ route('feed') }}">{{ __('Aller à l’Accueil') }}</a>
    </div>
  @endif
</div>
@endsection

@push('scripts')
<script>
// Lecture : la miniature devient le lecteur YouTube (youtube-nocookie) au toucher
(function () {
  var grid = document.getElementById('dgrid'); if (!grid) return;
  var playUrl = @json(route('discover.play', '__ID__'));
  grid.addEventListener('click', function (e) {
    var b = e.target.closest('.dthumb'); if (!b) return;
    var card = b.closest('.dcard'), id = card.dataset.yt;
    var f = document.createElement('iframe');
    f.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&playsinline=1&rel=0&modestbranding=1';
    f.title = card.querySelector('b').textContent; f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen'; f.allowFullscreen = true;
    b.replaceWith(f);
    fetch(playUrl.replace('__ID__', card.dataset.id), { method: 'POST', headers: { 'X-CSRF-TOKEN': window.Terrain.csrf } }).catch(function () {});
  });
  var more = document.getElementById('dmore');
  if (more) more.addEventListener('click', function () {
    more.disabled = true;
    fetch(more.dataset.next + (more.dataset.next.indexOf('?') > -1 ? '&' : '?') + 'fragment=1').then(function (r) {
      var nx = r.headers.get('X-Next'); return r.text().then(function (h) { return [h, nx]; });
    }).then(function (d) {
      grid.insertAdjacentHTML('beforeend', d[0]);
      if (d[1]) { more.dataset.next = d[1]; more.disabled = false; } else more.parentNode.remove();
    }).catch(function () { more.disabled = false; });
  });
})();
</script>
@endpush
