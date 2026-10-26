@extends('layouts.admin')
@section('title', 'Direct – Admin')
@section('content')
@include('admin._nav', ['title' => 'Matchs en direct', 'lead' => 'Les directs trouvés sur YouTube, ceux publiés par les clubs et ceux que tu ajoutes. Un direct non validé n’est pas visible.'])
@error('yt')<div class="msg err" role="alert">{{ $message }}</div>@enderror

@php
  $f = $edit;
  $fv = fn ($k, $d = '') => old($k, $f->{$k} ?? $d);
  $pending = $rows->where('approved', false);
  $src = ['youtube' => 'YouTube', 'club' => 'Club', 'admin' => 'Admin', 'mux' => 'Frikip'];
@endphp

<div class="kpis">
  <div class="kpi"><span>En direct maintenant</span><b>{{ $rows->where('approved', true)->where('status', 'direct')->count() }}</b><small>visibles sur le site</small></div>
  <div class="kpi"><span>À valider</span><b>{{ $pending->count() }}</b><small>trouvés sur YouTube ou envoyés par des clubs</small></div>
  <div class="kpi"><span>À venir</span><b>{{ $rows->where('status', 'prevu')->count() }}</b><small>matchs programmés</small></div>
  <div class="kpi"><span>Spectateurs (record)</span><b>{{ number_format((int) $rows->max('peak'), 0, ',', ' ') }}</b><small>sur un seul direct</small></div>
</div>

<div class="adm-grid">
  <section class="panel">
    <h2>Recherche automatique sur YouTube</h2>
    @if ($ytReady)
      <p class="meta" style="margin-top:-6px">Une recherche toutes les {{ $ytEvery }} minutes environ (quota gratuit de YouTube respecté).
        @if ($ytLast) Dernière : {{ \Illuminate\Support\Carbon::parse($ytLast['at'])->diffForHumans() }}, {{ $ytLast['found'] }} direct(s) trouvé(s).@endif</p>
      <form method="POST" action="{{ route('admin.live.sync') }}">@csrf<button class="btn" type="submit">Chercher maintenant</button></form>
    @else
      <p class="note">Pour trouver les matchs tout seul, ajoute une clé <code>YOUTUBE_API_KEY</code> dans le fichier <code>.env</code> (gratuit : console.cloud.google.com > API « YouTube Data API v3 » > Identifiants > Clé API).</p>
    @endif
    <h2 style="margin-top:18px">Diffusion par Frikip (Mux)</h2>
    @if ($muxReady)
      <p class="meta" style="margin-top:-6px">Active : les clubs peuvent filmer avec leur téléphone et diffuser sur Frikip. Coût chez Mux selon les minutes diffusées et regardées.</p>
    @else
      <p class="note">Facultatif et payant : avec <code>MUX_TOKEN_ID</code> et <code>MUX_TOKEN_SECRET</code> dans le <code>.env</code> (mux.com > Settings > Access Tokens), les clubs diffusent leurs matchs directement depuis un téléphone, sans passer par YouTube.</p>
    @endif
  </section>
  @include('admin._settings', ['group' => 'direct', 'title' => 'Réglages des directs'])
</div>

<section class="panel" id="formulaire">
  <h2>{{ $f ? 'Modifier « '.$f->title.' »' : 'Ajouter un direct' }}</h2>
  @if ($f && $f->stream_key && $f->status !== 'termine')
    <div class="note" style="margin-bottom:12px"><b>Diffuser :</b> serveur <code>{{ \App\Terrain\Growth\MuxLive::RTMP }}</code> · clé <code>{{ $f->stream_key }}</code><br><small>Application gratuite : Larix Broadcaster ou Prism Live (téléphone), OBS Studio (ordinateur). Le direct démarre tout seul quand la vidéo arrive.</small></div>
  @endif
  <form method="POST" action="{{ route('admin.live.save') }}" class="adform">@csrf
    @if ($f)<input type="hidden" name="id" value="{{ $f->id }}">@endif
    <div class="formgrid">
      <label class="wide">Titre<input name="title" value="{{ $fv('title') }}" maxlength="160" required></label>
      <label>Équipe à domicile<input name="home" value="{{ $fv('home') }}" maxlength="80" required></label>
      <label>Équipe à l’extérieur<input name="away" value="{{ $fv('away') }}" maxlength="80" required></label>
      <label>Sport<select name="sport">@foreach ($sports as $s)<option @selected($fv('sport', 'Football') === $s)>{{ $s }}</option>@endforeach</select></label>
      <label>Compétition<input name="competition" value="{{ $fv('competition') }}" maxlength="120"></label>
      <label>Pays<select name="country">@foreach ($countries as $c)<option @selected($fv('country', config('terrain.country_default', 'Guinée')) === $c)>{{ $c }}</option>@endforeach</select></label>
      <label>Ville<input name="city" value="{{ $fv('city') }}" maxlength="80"></label>
      <label>Coup d’envoi<input type="datetime-local" name="starts_at" value="{{ old('starts_at', $f && $f->starts_at ? \Illuminate\Support\Carbon::parse($f->starts_at)->format('Y-m-d\TH:i') : now()->addHour()->startOfHour()->format('Y-m-d\TH:i')) }}" required></label>
      <label>État<select name="status">@foreach (\App\Terrain\Growth\Live::STATUS as $k => $l)<option value="{{ $k }}" @selected($fv('status', 'prevu') === $k)>{{ $l }}</option>@endforeach</select></label>
      <label>Score domicile<input type="number" name="home_score" min="0" value="{{ $fv('home_score', 0) }}"></label>
      <label>Score extérieur<input type="number" name="away_score" min="0" value="{{ $fv('away_score', 0) }}"></label>
      <label>Minute<input name="minute" value="{{ $fv('minute') }}" maxlength="12" placeholder="37'"></label>
      @if (! $f && $muxReady)
        <label class="check wide"><input type="checkbox" name="mux" value="1" @checked(old('mux')) onchange="document.getElementById('aurl').required=!this.checked"> Diffuser avec Frikip (téléphone ou caméra) : pas besoin de lien</label>
      @endif
      <label class="wide">Lien du direct<input id="aurl" name="stream_url" type="url" value="{{ $fv('stream_url') }}" placeholder="https://www.youtube.com/live/… ou https://…m3u8" @unless ($f && $f->source === 'mux') required @endunless @if ($f && $f->source === 'mux') readonly @endif></label>
      <label>Match relié (facultatif)<input name="fixture_id" value="{{ $fv('fixture_id') }}" maxlength="40" placeholder="api-1208021"><small>Numéro du match sur la page Matchs : le bouton « Regarder dans Frikip » s’affiche sur ce match.</small></label>
      <label class="wide">Lien du replay (facultatif)<input name="replay_url" type="url" value="{{ $fv('replay_url') }}"></label>
    </div>
    <div class="formbar"><button class="btn" type="submit">{{ $f ? 'Enregistrer' : 'Ajouter le direct' }}</button>@if ($f)<a class="btn ghost" href="{{ route('admin.live') }}">Annuler</a>@endif</div>
  </form>
</section>

<section class="panel">
  <h2>Tous les directs</h2>
  @if ($rows->isEmpty())
    <p class="meta">Aucun direct pour l’instant.</p>
  @else
    <div class="mqueue">
      @foreach ($rows as $s)
        <div class="mcard">
          <div class="mthumb">@if ($s->thumbnail)<img src="{{ $s->thumbnail }}" alt="" loading="lazy" style="aspect-ratio:16/9;width:110px">@else<span class="vph" style="aspect-ratio:16/9;width:110px;display:block"></span>@endif</div>
          <div class="mbody">
            <span class="mkind">{{ $src[$s->source] ?? $s->source }}@if ($s->channel) · {{ $s->channel }}@endif</span>
            <b>{{ $s->home }}@if ($s->away) {{ $s->home_score }} – {{ $s->away_score }} {{ $s->away }}@endif</b>
            <small>{{ $s->title }}</small>
            <small>{{ $s->sport }} · {{ $s->country }}@if ($s->starts_at) · {{ \Illuminate\Support\Carbon::parse($s->starts_at)->format('d/m H:i') }}@endif · {{ number_format($s->views, 0, ',', ' ') }} vue(s)@if ($s->owner_name) · {{ $s->owner_name }}@endif</small>
            <span>@if (! $s->approved)<span class="pill warn">À valider</span>@endif <span class="pill {{ $s->status === 'direct' ? 'danger' : '' }}">{{ \App\Terrain\Growth\Live::STATUS[$s->status] ?? $s->status }}</span></span>
          </div>
          <div class="mact">
            <a class="btn ghost sm" href="{{ route('live.show', $s->id) }}" target="_blank" rel="noopener">Regarder</a>
            <form method="POST" action="{{ route('admin.live.action', $s->id) }}">@csrf<input type="hidden" name="action" value="{{ $s->approved ? 'retirer' : 'valider' }}"><button class="btn sm {{ $s->approved ? 'ghost' : '' }}" type="submit">{{ $s->approved ? 'Retirer du site' : 'Valider' }}</button></form>
            @if ($s->status !== 'termine')<form method="POST" action="{{ route('admin.live.action', $s->id) }}">@csrf<input type="hidden" name="action" value="{{ $s->status === 'direct' ? 'termine' : 'direct' }}"><button class="btn ghost sm" type="submit">{{ $s->status === 'direct' ? 'Marquer terminé' : 'Passer en direct' }}</button></form>@endif
            <a class="btn ghost sm" href="{{ route('admin.live', ['modifier' => $s->id]) }}#formulaire">Modifier</a>
            <form method="POST" action="{{ route('admin.live.action', $s->id) }}" onsubmit="return confirm('Supprimer ce direct ?')">@csrf<input type="hidden" name="action" value="supprimer"><button class="btn ghost sm" type="submit">Supprimer</button></form>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</section>
@endsection
