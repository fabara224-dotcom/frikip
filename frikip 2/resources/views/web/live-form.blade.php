@extends('layouts.web')

@section('title', ($s ? __('Gérer le direct') : __('Diffuser un match')).' – '.config('terrain.app_name'))

@php
  $v = fn ($k, $d = '') => old($k, $s->{$k} ?? $d);
  $start = $s && $s->starts_at ? \Illuminate\Support\Carbon::parse($s->starts_at)->format('Y-m-d\TH:i') : now()->addHour()->startOfHour()->format('Y-m-d\TH:i');
@endphp

@section('content')
<div class="page gut">
  <div class="narrow pub">
    <p class="meta" style="margin:0"><a href="{{ route('live') }}">← {{ __('En direct') }}</a></p>
    <h1>{{ $s ? $s->home.' – '.$s->away : __('Diffuser un match') }}</h1>

    @if ($s)
      @unless ($s->approved)<p class="note">{{ __('En attente de validation par l’équipe : le direct n’est pas encore visible.') }}</p>@endunless
      <section class="card scorepad" id="pad">
        <div class="lscore big"><b>{{ $s->home }}</b><em><span id="pad-h">{{ $s->home_score }}</span> – <span id="pad-a">{{ $s->away_score }}</span></em><b>{{ $s->away }}</b></div>
        <div class="padbtns">
          <button type="button" class="btn" data-q="home+">+1 {{ $s->home }}</button>
          <button type="button" class="btn" data-q="away+">+1 {{ $s->away }}</button>
          <button type="button" class="btn ghost sm" data-q="home-">−1 {{ $s->home }}</button>
          <button type="button" class="btn ghost sm" data-q="away-">−1 {{ $s->away }}</button>
        </div>
        <label for="pad-min">{{ __('Minute ou période') }}<input id="pad-min" value="{{ $s->minute }}" placeholder="{{ __('ex. 37\' ou Mi-temps') }}" maxlength="12"></label>
        <div class="padbtns">
          <button type="button" class="btn ghost" data-q="minute">{{ __('Mettre à jour la minute') }}</button>
          @if ($s->status !== 'direct')<button type="button" class="btn live" data-q="direct">● {{ __('Commencer le direct') }}</button>@endif
          @if ($s->status === 'direct')<button type="button" class="btn dark" data-q="termine">{{ __('Fin du match') }}</button>@endif
        </div>
        <p class="meta" id="pad-msg" style="margin:0">{{ __(\App\Terrain\Growth\Live::STATUS[$s->status]) }} · <a href="{{ route('live.show', $s->id) }}">{{ __('Voir la page du direct') }}</a></p>
      </section>
      @if (($s->source ?? '') === 'mux' && $s->stream_key && $s->status !== 'termine')
        <section class="card muxbox">
          <h2 style="margin:0">{{ __('Diffuser depuis ton téléphone ou ta caméra') }}</h2>
          <p class="meta" style="margin:0">{{ __('Dans l’application de diffusion, choisis « RTMP » ou « Serveur personnalisé » et colle ces deux informations. Le direct démarre tout seul sur Frikip quand la vidéo arrive.') }}</p>
          <label>{{ __('Adresse du serveur') }}<span class="copyrow"><input readonly value="{{ \App\Terrain\Growth\MuxLive::RTMP }}" onclick="this.select()"><button type="button" class="btn ghost sm" data-copy="{{ \App\Terrain\Growth\MuxLive::RTMP }}">{{ __('Copier') }}</button></span>
            <small>{{ __('Si ton application refuse « rtmps », utilise :u', ['u' => \App\Terrain\Growth\MuxLive::RTMP_PLAIN]) }}</small></label>
          <label>{{ __('Clé de diffusion (secrète)') }}<span class="copyrow"><input readonly type="password" value="{{ $s->stream_key }}" onfocus="this.type='text';this.select()" onblur="this.type='password'"><button type="button" class="btn ghost sm" data-copy="{{ $s->stream_key }}">{{ __('Copier') }}</button></span>
            <small>{{ __('Ne la donne qu’à la personne qui filme. Partagée par erreur ?') }} <button type="button" class="linkbtn" data-q="cle">{{ __('Changer la clé') }}</button></small></label>
          <ul class="plan-list">
            <li>{{ __('Téléphone : Larix Broadcaster ou Prism Live (gratuits, Android et iPhone).') }}</li>
            <li>{{ __('Ordinateur ou caméra : OBS Studio (Paramètres > Stream > Service « Personnalisé »).') }}</li>
            <li>{{ __('Conseil : téléphone à l’horizontale, sur un pied, branché au chargeur, en 4G ou Wi-Fi stable (2 Mbit/s minimum).') }}</li>
          </ul>
        </section>
      @endif
      <h2>{{ __('Informations du match') }}</h2>
    @else
      <p class="pub-lead">{{ __('Lance ton direct sur YouTube Live, Facebook Live ou ton service de diffusion, puis colle le lien ici. Frikip l’affiche avec le score en direct.') }}</p>
    @endif

    <form method="POST" action="{{ $s ? route('live.update', $s->id) : route('live.store') }}" class="card">@csrf
      <label for="title">{{ __('Titre') }}<input id="title" name="title" value="{{ $v('title') }}" maxlength="160" required placeholder="{{ __('ex. Finale du tournoi U17 de Kaloum') }}"></label>
      <div class="phonerow">
        <label for="home">{{ __('Équipe à domicile') }}<input id="home" name="home" value="{{ $v('home') }}" maxlength="80" required></label>
        <label for="away">{{ __('Équipe à l’extérieur') }}<input id="away" name="away" value="{{ $v('away') }}" maxlength="80" required></label>
      </div>
      <div class="phonerow">
        <label for="sport">{{ __('Sport') }}<select id="sport" name="sport">@foreach ($sports as $sp)<option value="{{ $sp }}" @selected($v('sport', 'Football') === $sp)>@td($sp)</option>@endforeach</select></label>
        <label for="competition">{{ __('Compétition') }}<input id="competition" name="competition" value="{{ $v('competition') }}" maxlength="120"></label>
      </div>
      <div class="phonerow">
        <label for="country">{{ __('Pays') }}<select id="country" name="country">@foreach ($countries as $c)<option value="{{ $c }}" @selected($v('country', $country) === $c)>{{ \App\Terrain\DataLang::t($c) }}</option>@endforeach</select></label>
        <label for="city">{{ __('Ville') }}<input id="city" name="city" value="{{ $v('city') }}" maxlength="80"></label>
      </div>
      <label for="starts_at">{{ __('Coup d’envoi') }}<input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $start) }}" required></label>
      @if (! $s && $muxReady)
        <fieldset class="langfs"><legend><b>{{ __('Comment tu diffuses ?') }}</b></legend>
          <div class="plans">
            <label class="opt"><input type="radio" name="mode" value="frikip" @checked(old('mode', 'frikip') === 'frikip') onchange="document.getElementById('urlbox').hidden=true;document.getElementById('stream_url').required=false"><span>{{ __('Avec Frikip') }}</span><span class="meta">{{ __('Filme avec ton téléphone ou ta caméra : Frikip te donne une clé de diffusion.') }}</span></label>
            <label class="opt"><input type="radio" name="mode" value="lien" @checked(old('mode') === 'lien') onchange="document.getElementById('urlbox').hidden=false;document.getElementById('stream_url').required=true"><span>{{ __('J’ai déjà un lien') }}</span><span class="meta">{{ __('YouTube Live, Facebook Live, Vimeo…') }}</span></label>
          </div>
        </fieldset>
      @endif
      @if (! $s || ($s->source ?? '') !== 'mux')
      <label for="stream_url" id="urlbox" @if (! $s && $muxReady && old('mode', 'frikip') === 'frikip') hidden @endif>{{ __('Lien du direct') }}<small>{{ __('YouTube Live, Facebook Live, Vimeo ou lien .m3u8 de ton service de diffusion') }}</small>
        <input id="stream_url" name="stream_url" type="url" value="{{ $v('stream_url') }}" placeholder="https://www.youtube.com/live/…" @unless (! $s && $muxReady && old('mode', 'frikip') === 'frikip') required @endunless></label>
      @else
        <input type="hidden" name="stream_url" value="{{ $s->stream_url }}">
      @endif
      @if ($s)
        <label for="replay_url">{{ __('Lien du replay (facultatif)') }}<input id="replay_url" name="replay_url" type="url" value="{{ $v('replay_url') }}" placeholder="https://"></label>
      @else
        <label class="check"><input type="checkbox" name="rights_ok" value="1" @checked(old('rights_ok'))> {{ __('J’ai le droit de diffuser ce match (mon club, mon tournoi, ou accord des organisateurs).') }}</label>
      @endif
      @if ($errors->any())<p class="field-error">{{ $errors->first() }}</p>@endif
      <button class="btn big" type="submit">{{ $s ? __('Enregistrer') : __('Publier le direct') }}</button>
    </form>
    @unless ($s)
      <details class="more-opts"><summary><b>{{ __('Comment diffuser avec un téléphone ?') }}</b></summary>
        <ol class="howto" style="padding:0 16px 14px 36px">
          <li><b>YouTube</b><span>{{ __('Application YouTube > bouton + > « Passer au direct ». Copie le lien du direct.') }}</span></li>
          <li><b>Facebook</b><span>{{ __('Page de ton club > « Vidéo en direct ». Copie le lien de la publication.') }}</span></li>
          <li><b>{{ __('Meilleure qualité') }}</b><span>{{ __('Avec une caméra ou plusieurs téléphones, utilise un service de diffusion (Cloudflare Stream, Mux…) et colle le lien .m3u8.') }}</span></li>
        </ol>
      </details>
    @endunless
  </div>
</div>
@endsection

@if ($s)
@push('scripts')
<script>
(function () {
  var pad = document.getElementById('pad'), url = @json(route('live.update', $s->id)), csrf = document.querySelector('meta[name=csrf-token]').content;
  document.querySelectorAll('[data-copy]').forEach(function (b) { b.addEventListener('click', function () { (navigator.clipboard ? navigator.clipboard.writeText(b.dataset.copy) : Promise.reject()).then(function () { b.textContent = @json(__('Copié')); }, function () { prompt('', b.dataset.copy); }); }); });
  document.querySelectorAll('.muxbox [data-q]').forEach(function (b) { b.addEventListener('click', function () { if (!confirm(@json(__('Changer la clé ? L’ancienne ne marchera plus.')))) return; var f = new FormData(); f.append('quick', 'cle'); fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: f }).then(function () { location.reload(); }); }); });
  pad.addEventListener('click', function (e) {
    var b = e.target.closest('[data-q]'); if (!b) return;
    var q = b.dataset.q, body = new FormData(); body.append('quick', q); body.append('minute', document.getElementById('pad-min').value);
    b.disabled = true;
    fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: body }).then(function (r) { return r.json(); }).then(function (d) {
      document.getElementById('pad-h').textContent = d.home; document.getElementById('pad-a').textContent = d.away;
      if (q === 'direct' || q === 'termine') location.reload();
    }).catch(function () {}).then(function () { b.disabled = false; });
  });
})();
</script>
@endpush
@endif
