@php
    $fmt = fn ($n) => $n >= 1000 ? str_replace('.', app()->getLocale() === 'en' ? '.' : ',', round($n / 1000, 1)).' k' : (string) $n;
    $heart = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7.5-4.6-9.6-9.5A5.4 5.4 0 0 1 12 6a5.4 5.4 0 0 1 9.6 5.5C19.5 16.4 12 21 12 21z"/></svg>';
    $bubble = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3C6.5 3 2 6.9 2 11.6c0 2.6 1.4 4.9 3.6 6.5L5 22l4.3-2.4c.9.2 1.8.3 2.7.3 5.5 0 10-3.9 10-8.6S17.5 3 12 3z"/></svg>';
    $share = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 4l8 8-8 8v-5c-6 0-9.5 1.8-12 6 1-6 4-11.5 12-12.5z"/></svg>';
    $soundOff = '<svg class="i-off" viewBox="0 0 24 24" fill="currentColor"><path d="M4 9h4l5-4v14l-5-4H4z"/><path d="M16 9l5 6M21 9l-5 6" stroke="currentColor" stroke-width="2"/></svg>';
    $soundOn = '<svg class="i-on" viewBox="0 0 24 24" fill="currentColor"><path d="M4 9h4l5-4v14l-5-4H4z"/><path d="M16 8.5a5 5 0 0 1 0 7M18.5 6a8.5 8.5 0 0 1 0 12" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>';
    $langNames = ['fr' => __('français'), 'en' => __('anglais')];
    $page = app()->getLocale();
    $lite = request()->cookie('terrain_lite') === '1';
    $me ??= null;
@endphp
@foreach ($items as $k => $it)
      @if (isset($it['live']))
        @include('web.partials.feed-live', ['s' => $it['live']])
        @continue
      @endif
      @if (isset($it['ad']))
        @include('web.partials.feed-ad', ['ad' => $it['ad']])
        @continue
      @endif
      @php
        $a = $it['athlete']; $v = $it['video'];
        $c = \App\Terrain\SportFields::color($a['sport']);
        $first = \Illuminate\Support\Arr::first($a['fields']) ?? $a['sport'];
        $title = \App\Terrain\DataLang::video($v, 'title');
        $caption = \App\Terrain\DataLang::video($v, 'caption');
        $spoken = $v['lang'] ?? 'none';
        $audio = ! empty($v['has_audio']);
        $tracks = $v['tracks'] ?? [];
        $defaultTrack = \App\Terrain\Growth\Subtitles::defaultFor($v);
      @endphp
      <section class="tki" data-video="{{ $v['id'] }}" data-athlete="{{ $a['id'] }}" data-sport="{{ $a['sport'] }}" data-audio="{{ $audio ? 1 : 0 }}" @if ($defaultTrack) data-subs="{{ $defaultTrack }}" @endif>
        <div class="vid" style="background:linear-gradient(170deg,{{ $c[0] }},{{ $c[1] }})">
          <video src="{{ $v['url'] }}" @if (! empty($v['poster']))poster="{{ $v['poster'] }}" @endif muted playsinline loop preload="{{ $lite ? 'none' : ($k < 2 ? 'auto' : 'metadata') }}" aria-label="{{ __('Vidéo de :name : :title', ['name' => $a['name'], 'title' => $title]) }}">
            @foreach ($tracks as $l)
              <track kind="subtitles" srclang="{{ $l }}" label="{{ \App\Terrain\Growth\Subtitles::LANGS[$l] ?? $l }}" src="{{ route('videos.subtitles', [$v['id'], $l]) }}">
            @endforeach
          </video>
          <div class="shade"></div>
          @if ($caption)<div class="cap">{{ $caption }}</div>@endif
          <div class="subs" aria-live="off" hidden></div>
          <div class="pp" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 4l13 8-13 8z"/></svg></div>
          @if ($audio)<button class="tap-sound" type="button" data-sound hidden>🔊 {{ __('Touche pour le son') }}</button>@endif

          <div class="rail">
            <div class="rav">
              <a href="{{ route('athletes.show', $a['id']) }}" aria-label="{{ __('Profil de :name', ['name' => $a['name']]) }}"><x-av :name="$a['name']" :sport="$a['sport']" /></a>
              @unless (in_array($a['id'], $following, true) || ($me['athlete_id'] ?? null) === $a['id'])
                <button class="plus" type="button" data-follow="{{ $a['id'] }}" aria-label="{{ __('Suivre :name', ['name' => $a['name']]) }}">+</button>
              @endunless
            </div>
            <button class="rb {{ $v['liked'] ? 'on' : '' }}" type="button" data-like="{{ $v['id'] }}" aria-pressed="{{ $v['liked'] ? 'true' : 'false' }}" aria-label="{{ __('J’aime') }}">{!! $heart !!}<span>{{ $fmt($v['likes']) }}</span></button>
            <button class="rb" type="button" data-comments="{{ $v['id'] }}" aria-label="{{ __('Commentaires') }}">{!! $bubble !!}<span>{{ $fmt($v['comments']) }}</span></button>
            <button class="rb" type="button" data-share="{{ route('feed', ['v' => $v['id']]) }}" data-share-video="{{ $v['id'] }}" data-share-text="{{ $a['name'] }} – {{ $title }}" aria-label="{{ __('Partager') }}">{!! $share !!}<span>{{ ($v['shares'] ?? 0) ? $fmt($v['shares']) : __('Partager') }}</span></button>
            @if ($audio)
              <button class="rb snd-btn" type="button" data-sound aria-label="{{ __('Activer le son') }}">{!! $soundOff !!}{!! $soundOn !!}<span>{{ __('Son') }}</span></button>
            @else
              <span class="rb mute-only" title="{{ __('Cette vidéo n’a pas de son') }}">{!! $soundOff !!}<span>{{ __('Sans son') }}</span></span>
            @endif
            @if ($tracks)
              <button class="rb cc {{ $defaultTrack ? 'on' : '' }}" type="button" data-cc aria-pressed="{{ $defaultTrack ? 'true' : 'false' }}" aria-label="{{ __('Sous-titres') }}"><b>CC</b><span>{{ $defaultTrack ? strtoupper($defaultTrack) : __('Non') }}</span></button>
            @endif
          </div>

          <div class="tmeta">
            <a class="who" href="{{ route('athletes.show', $a['id']) }}">{{ $a['name'] }} @if (! empty($a['star']))<x-star />@endif
              @if ($a['age'] < 18)<span class="pill warn">-18</span>@endif</a>
            <div class="what">{{ $title }}</div>
            @if (! empty($a['specialty']))<div class="spec-tag">★ @td($a['specialty'])</div>@endif
            <div class="spt">@td($a['sport']) · @td($first) · {{ $a['age'] }} {{ __('ans') }} · {{ $a['city'] }}</div>
            <div class="vstats">{{ $fmt($v['views'] ?? 0) }} {{ ($v['views'] ?? 0) > 1 ? __('vues') : __('vue') }}@if (! empty($a['followers'])) · {{ $fmt($a['followers']) }} {{ $a['followers'] > 1 ? __('abonnés') : __('abonné') }}@endif</div>
            @if (! empty($a['demo']))<div class="demo-tag">{{ __('Profil fictif · exemple') }}</div>@endif
            @if (! empty($v['credit']))<div class="credit">{{ __('Vidéo d’illustration') }} : <a href="{{ $v['credit_url'] }}" target="_blank" rel="noopener">{{ $v['credit'] }}</a> / <a href="{{ $v['source_url'] }}" target="_blank" rel="noopener">{{ $v['source_name'] ?? 'Pexels' }}</a></div>@endif
            @if ($audio)
              <div class="snd">♫ {{ isset($langNames[$spoken]) ? __('Commentaire en :lang', ['lang' => $langNames[$spoken]]) : __('Son original') }}@if ($defaultTrack) · {{ __('sous-titres :lang', ['lang' => $langNames[$defaultTrack]]) }}@endif @if (! empty($v['voice_demo']))· {{ __('voix de synthèse (exemple)') }}@endif</div>
            @endif
          </div>
          <div class="prog"><i></i></div>
        </div>
        <div class="tkbar">
          <a href="{{ route('athletes.show', $a['id']) }}">{{ __('Voir le profil') }}</a>
          @if (($me['athlete_id'] ?? null) === $a['id'])<a href="{{ route('videos.edit', $v['id']) }}">{{ __('Sous-titres') }}</a>@endif
          <button type="button" data-report="{{ $v['id'] }}" aria-label="{{ __('Signaler cette vidéo') }}">•••</button>
        </div>
      </section>
@endforeach
