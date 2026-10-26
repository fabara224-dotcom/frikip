{{-- Où regarder le match (hors de la zone rafraîchie toutes les 15 s, pour ne pas couper la vidéo). --}}
@php
  $isReal = ($m['source'] ?? null) === 'api';
  // Lien unique du partenaire (Admin > Diffuseurs) : proposé sur tous les matchs qui n'ont pas leur propre vidéo
  $partner = app(\App\Terrain\Growth\PartnerLink::class)->get();
  $ownVideo = ! empty($m['live_id']);
@endphp
    @if ($m['status'] !== 'finished' && ($isReal || ($partner && ! $ownVideo)))
      @php
        $bc = app(\App\Terrain\Growth\Broadcasters::class);
        $inside = $isReal ? $bc->inFrikip($m) : null;
        $tv = $isReal ? $bc->for($m['league_id'] ?? null) : [];
      @endphp
      <section class="watchbox">
        <h2>{{ $m['status'] === 'live' ? __('Regarder en direct') : __('Où regarder ce match') }}</h2>
        @if ($inside && $inside['type'] === 'stream')
          <a class="btn live" href="{{ route('live.show', $inside['stream']->id) }}">● {{ __('Regarder dans :app', ['app' => config('terrain.app_name')]) }}</a>
        @elseif ($inside)
          <div class="player watch-player">
            @if ($inside['player']['type'] === 'iframe')<iframe src="{{ $inside['player']['src'] }}" title="{{ $m['home'] }} – {{ $m['away'] }}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
            @elseif (in_array($inside['player']['type'], ['hls', 'video'], true))<video id="matchstream" controls playsinline muted data-src="{{ $inside['player']['src'] }}" data-type="{{ $inside['player']['type'] }}"></video>
              <script src="https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.5.13/hls.min.js"></script>
              <script>(function () { var v = document.getElementById('matchstream'); if (!v || v._on) return; v._on = 1; var s = v.dataset.src;
                if (v.dataset.type === 'hls' && !v.canPlayType('application/vnd.apple.mpegurl') && window.Hls && Hls.isSupported()) { var h = new Hls(); h.loadSource(s); h.attachMedia(v); } else { v.src = s; } })();</script>
            @else<a class="player-msg" href="{{ $inside['broadcaster']->id ? route('watch', $inside['broadcaster']->id) : $inside['broadcaster']->url }}" target="_blank" rel="noopener"><span class="btn">{{ __('Regarder sur :b', ['b' => $inside['broadcaster']->name]) }}</span></a>@endif
          </div>
          <p class="meta">{{ __('Diffusé avec :b, partenaire officiel.', ['b' => $inside['broadcaster']->name]) }}</p>
        @elseif ($partner)
          @if ($partner->inside)
            @include('web.partials.stream-player', ['player' => $partner->player, 'title' => $m['home'].' – '.$m['away'], 'linkUrl' => $partner->url, 'linkName' => $partner->name])
            <p class="meta">{{ __('Diffusé avec :b, partenaire officiel.', ['b' => $partner->name]) }}@if ($partner->note) {{ $partner->note }}@endif</p>
          @else
            <a class="btn live" href="{{ $partner->url }}" target="_blank" rel="noopener">● {{ __('Regarder sur :b', ['b' => $partner->name]) }}</a>
            @if ($partner->note)<p class="meta" style="margin:6px 0 0">{{ $partner->note }}</p>@endif
          @endif
        @endif
        @if ($tv)
          <div class="tvlist">
            @foreach ($tv as $b)
              @continue($inside && $inside['type'] === 'partner' && $inside['broadcaster']->id === $b->id)
              <a class="tvrow" href="{{ route('watch', $b->id) }}" target="_blank" rel="noopener sponsored"><span><b>{{ $b->name }}</b>@if ($b->note)<small>{{ $b->note }}</small>@endif</span><span class="btn sm">{{ __('Regarder') }}</span></a>
            @endforeach
          </div>
          <p class="meta" style="margin:6px 0 0">{{ __('Diffuseurs officiels dans ton pays (:c). Un abonnement peut être nécessaire.', ['c' => \App\Terrain\DataLang::t(\App\Terrain\Region::viewer())]) }}</p>
        @elseif (! $inside && ! $partner)
          <p class="meta" style="margin:0">{{ __('Score, buts et cartons en direct ici. Le diffuseur officiel de ton pays sera bientôt indiqué.') }}</p>
        @endif
      </section>
    @endif

