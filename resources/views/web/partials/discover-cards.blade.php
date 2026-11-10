@foreach ($list as $v)
  <article class="dcard" data-yt="{{ $v->youtube_id }}" data-id="{{ $v->id }}">
    <button class="dthumb" type="button" aria-label="{{ __('Regarder : :t', ['t' => $v->title]) }}">
      @if ($v->thumbnail)<img src="{{ $v->thumbnail }}" alt="" loading="lazy" width="480" height="360">@endif
      <span class="dplay" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M8 5.5v13l10.5-6.5z"/></svg></span>
      <span class="ddur">{{ \App\Terrain\Growth\Discover::duration((int) $v->duration) }}</span>
    </button>
    <div class="dmeta">
      <b>{{ $v->title }}</b>
      <small>{{ __('Vidéo YouTube') }} · <a href="https://www.youtube.com/channel/{{ $v->channel_id }}" target="_blank" rel="noopener">{{ $v->channel }}</a>@if ($v->topic) · {{ __($v->topic) }}@endif</small>
    </div>
  </article>
@endforeach
