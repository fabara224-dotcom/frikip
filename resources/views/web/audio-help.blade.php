@extends('layouts.web')

@section('title', __('Aide audio').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:760px">
    <h1>{{ __('Aide audio') }}</h1>
    <p class="meta">{{ __('Des explications à écouter, en soussou, pular, malinké et français, pour ceux qui préfèrent écouter plutôt que lire.') }}</p>
    @php
      $available = 0;
      foreach ($pages as $p => $l) { foreach ($langs as $code => $n) { if (is_file(public_path("aide/{$code}/{$p}.mp3"))) { $available++; } } }
    @endphp
    @if (! $available)
      <p class="note">{{ __('Les enregistrements arrivent bientôt.') }} @if ($demo)<br><b>Pour l'équipe :</b> enregistrez chaque message (1 minute max) avec une personne qui parle la langue, puis déposez les fichiers MP3 dans <code>public/aide/&lt;langue&gt;/&lt;sujet&gt;.mp3</code> (langues : {{ implode(', ', array_keys($langs)) }} ; sujets : {{ implode(', ', array_keys($pages)) }}). Ils apparaissent ici automatiquement.@endif</p>
    @endif
    <div class="list">
      @foreach ($pages as $p => $label)
        <div class="li" style="flex-wrap:wrap">
          <span class="grow t">{{ __($label) }}</span>
          <span class="row" style="gap:6px;flex-wrap:wrap">
            @foreach ($langs as $code => $name)
              @if (is_file(public_path("aide/{$code}/{$p}.mp3")))
                <button class="btn ghost sm" type="button" data-audio="{{ asset("aide/{$code}/{$p}.mp3") }}">▶ {{ $name }}</button>
              @else
                <span class="pill" style="opacity:.5">{{ $name }} · {{ __('bientôt') }}</span>
              @endif
            @endforeach
          </span>
        </div>
      @endforeach
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var player = new Audio(), current = null;
  document.querySelectorAll('[data-audio]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (current === b && !player.paused) { player.pause(); b.textContent = b.textContent.replace('❚❚', '▶'); return; }
      if (current) current.textContent = current.textContent.replace('❚❚', '▶');
      player.src = b.dataset.audio; player.play(); current = b; b.textContent = b.textContent.replace('▶', '❚❚');
    });
  });
  player.onended = function () { if (current) current.textContent = current.textContent.replace('❚❚', '▶'); };
})();
</script>
@endpush
