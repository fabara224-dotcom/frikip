{{-- Vidéo « Découvrir » dans l'Accueil : lecteur YouTube officiel, chargé quand la carte est à l'écran, chaîne créditée. --}}
<section class="tki liveslot discslot" data-live="yt" data-disc="{{ $v->id }}">
  <div class="vid" style="background:#0B2318">
    <iframe data-src="https://www.youtube-nocookie.com/embed/{{ $v->youtube_id }}?autoplay=1&mute=1&playsinline=1&rel=0&modestbranding=1&loop=1&playlist={{ $v->youtube_id }}" title="{{ $v->title }}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
    @if ($v->thumbnail)<img src="{{ $v->thumbnail }}" alt="" loading="lazy" class="discposter">@endif
    <div class="tmeta">
      <span class="disctag">▶ {{ __('Découvrir') }}@if ($v->topic) · {{ __($v->topic) }}@endif</span>
      <div class="who">{{ \Illuminate\Support\Str::limit($v->title, 90) }}</div>
      <div class="spt">{{ __('Vidéo YouTube') }} · {{ $v->channel }}</div>
    </div>
  </div>
  <div class="tkbar"><a href="{{ route('discover', $v->topic ? ['theme' => $v->topic] : []) }}">{{ __('Plus de vidéos à découvrir') }}</a></div>
</section>
