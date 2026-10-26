{{-- Publicité dans le fil (Admin > Publicités). Retirée avec l'option « Sans pub ». --}}
<section class="tki adslot" data-ad="{{ $ad['id'] }}">
  <div class="vid" style="background:#0B2318">
    <video src="{{ $ad['video'] }}" @if ($ad['poster'])poster="{{ $ad['poster'] }}" @endif muted playsinline loop preload="metadata" aria-label="{{ __('Publicité : :t', ['t' => $ad['title']]) }}"></video>
    <div class="shade"></div>
    <div class="pp" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 4l13 8-13 8z"/></svg></div>
    <div class="rail"><button class="rb snd-btn" type="button" data-sound aria-label="{{ __('Activer le son') }}"><svg class="i-off" viewBox="0 0 24 24" fill="currentColor"><path d="M4 9h4l5-4v14l-5-4H4z"/><path d="M16 9l5 6M21 9l-5 6" stroke="currentColor" stroke-width="2"/></svg><svg class="i-on" viewBox="0 0 24 24" fill="currentColor"><path d="M4 9h4l5-4v14l-5-4H4z"/><path d="M16 8.5a5 5 0 0 1 0 7M18.5 6a8.5 8.5 0 0 1 0 12" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg><span>{{ __('Son') }}</span></button></div>
    <div class="tmeta">
      <span class="adtag">{{ __('Publicité') }}</span>
      <div class="who">{{ $ad['advertiser'] }}</div>
      <div class="what">{{ $ad['title'] }}</div>
      @if ($ad['link'])<a class="btn adcta" href="{{ $ad['link'] }}" target="_blank" rel="noopener sponsored">{{ __($ad['cta']) }}</a>@endif
    </div>
    <div class="prog"><i></i></div>
  </div>
  <div class="tkbar">
    <a href="{{ route('premium', 'sans_pub') }}">{{ __('Retirer les pubs') }}</a>
  </div>
</section>
