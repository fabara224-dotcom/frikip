@extends('layouts.admin')
@section('title', 'Diffuseurs – Admin')
@section('content')
@include('admin._nav', ['title' => 'Diffuseurs officiels', 'lead' => 'Pour chaque match de Premier League, Ligue des champions, Liga, Serie A, Bundesliga…, Frikip montre où le regarder en direct dans le pays du visiteur. Avec un contrat, le flux du diffuseur se regarde directement dans Frikip.'])

@php
  $f = $edit;
  $fc = $f ? (json_decode((string) $f->competitions, true) ?: []) : [];
  $fp = $f ? (json_decode((string) $f->countries, true) ?: []) : [];
@endphp

@unless ($apiReady)
  <p class="note">Les matchs des grands championnats viennent d’API-Football : ajoute <code>API_FOOTBALL_KEY</code> dans le <code>.env</code> (formule payante conseillée pour le direct : mises à jour toutes les 15 secondes).</p>
@endunless
<p class="note">Les droits changent chaque saison et selon les pays. Les diffuseurs ci-dessous sont des points de départ, <b>désactivés</b> : vérifie qui diffuse quoi chez toi, mets ton lien partenaire (affiliation) s’il y en a un, puis active-les.</p>

<div class="adm-grid" id="lien-unique">
  @include('admin._settings', ['group' => 'partenaire', 'title' => 'Lien unique pour tous les matchs'])
  <section class="panel">
    <h2>Comment ça marche</h2>
    <p class="meta">Ton partenaire te donne <b>un seul lien</b> pour tous ses matchs : colle-le à gauche, active-le et enregistre. Il apparaît alors :</p>
    <ul class="meta" style="margin:0 0 10px;padding-left:18px;line-height:1.6">
      <li>sur la page de <b>chaque match</b> (« Regarder en direct ») ;</li>
      <li>sur la page <b>En direct</b> et dans l’onglet <b>Matchs</b> ;</li>
      <li>en haut du <b>fil</b> (« Matchs en direct »).</li>
    </ul>
    <p class="meta">Coche « Accord écrit signé » seulement si le partenaire t’a autorisé par écrit à intégrer sa vidéo dans Frikip, et s’il a lui-même les droits des matchs. Sinon, le bouton ouvre son site.</p>
    @error('partner_url')<p class="field-error">{{ $message }}</p>@enderror
    @if ($partner)
      <p><span class="pill ok">Actif</span> {{ $partner->name }} · {{ $partner->inside ? 'vidéo dans Frikip' : 'ouvre le site du partenaire' }}</p>
      <div class="formbar"><a class="btn ghost" href="{{ route('live.partner') }}" target="_blank" rel="noopener">Voir la page des matchs</a></div>
    @else
      <p><span class="pill">Inactif</span></p>
    @endif
  </section>
</div>

<div class="adm-grid">
  <section class="panel">
    <h2>{{ $f ? 'Modifier '.$f->name : 'Ajouter un diffuseur' }}</h2>
    <form method="POST" action="{{ route('admin.broadcasters.save') }}" class="adform">@csrf
      @if ($f)<input type="hidden" name="id" value="{{ $f->id }}">@endif
      <div class="formgrid">
        <label>Nom<input name="name" value="{{ old('name', $f->name ?? '') }}" required maxlength="80" placeholder="ex. Canal+ Afrique"></label>
        <label>Ordre (1 = en premier)<input name="priority" type="number" min="1" max="20" value="{{ old('priority', $f->priority ?? 5) }}"></label>
        <label class="wide">Lien « Regarder » (page officielle ou lien partenaire)<input name="url" type="url" value="{{ old('url', $f->url ?? '') }}" required placeholder="https://"></label>
        <label class="wide">Précision affichée<input name="note" value="{{ old('note', $f->note ?? '') }}" maxlength="160" placeholder="ex. Application Canal+, abonnement Les Chaînes Canal+"></label>
      </div>
      <fieldset class="countrypick"><legend>Compétitions <small>(aucune cochée = toutes)</small></legend>
        @foreach ($comps as $id => $name)<label class="check"><input type="checkbox" name="competitions[]" value="{{ $id }}" @checked(in_array($id, array_map('intval', old('competitions', $fc)), true))> {{ $name }}</label>@endforeach
      </fieldset>
      <fieldset class="countrypick"><legend>Pays <small>(aucun coché = tous)</small></legend>
        @foreach ($countries as $c)<label class="check"><input type="checkbox" name="countries[]" value="{{ $c }}" @checked(in_array($c, old('countries', $fp), true))> {{ $c }}</label>@endforeach
      </fieldset>
      <fieldset class="countrypick"><legend>Contrat de diffusion</legend>
        <label class="check wide"><input type="checkbox" name="contract" value="1" @checked(old('contract', $f->contract ?? false))> Contrat signé : le flux se regarde directement dans Frikip</label>
        <label class="wide" style="flex:1 1 100%">Lien du flux fourni par le diffuseur<input name="embed_url" type="url" value="{{ old('embed_url', $f->embed_url ?? '') }}" placeholder="https://…m3u8 ou lien d’intégration"><small>Seulement avec un accord écrit du diffuseur.</small></label>
      </fieldset>
      <label class="check" style="margin-top:12px"><input type="checkbox" name="active" value="1" @checked(old('active', $f->active ?? true))> Afficher sur les matchs</label>
      <div class="formbar"><button class="btn" type="submit">{{ $f ? 'Enregistrer' : 'Ajouter' }}</button>@if ($f)<a class="btn ghost" href="{{ route('admin.broadcasters') }}">Annuler</a>@endif</div>
    </form>
  </section>
  <section class="panel">
    <h2>Diffuseurs</h2>
    @foreach ($rows as $b)
      @php
        $bc = array_map(fn ($i) => $comps[(int) $i] ?? $i, json_decode((string) $b->competitions, true) ?: []);
        $bp = json_decode((string) $b->countries, true) ?: [];
      @endphp
      <div class="line {{ $b->active ? '' : 'muted' }}">
        <span><b>{{ $b->name }} @if ($b->contract)<span class="pill ok">Contrat</span>@endif</b>
          <small>{{ $bc ? implode(', ', $bc) : 'Toutes les compétitions' }}</small>
          <small>{{ $bp ? count($bp).' pays' : 'Tous les pays' }} · {{ number_format($b->clicks, 0, ',', ' ') }} clic(s)</small></span>
        <span class="actbtns">
          <a class="btn ghost sm" href="{{ route('admin.broadcasters', ['modifier' => $b->id]) }}">Modifier</a>
          <form method="POST" action="{{ route('admin.broadcasters.action', $b->id) }}">@csrf<button class="btn sm {{ $b->active ? 'ghost' : '' }}" type="submit">{{ $b->active ? 'Masquer' : 'Activer' }}</button></form>
          <form method="POST" action="{{ route('admin.broadcasters.action', $b->id) }}" onsubmit="return confirm('Supprimer ce diffuseur ?')">@csrf<input type="hidden" name="action" value="supprimer"><button class="btn ghost sm" type="submit">Supprimer</button></form>
        </span>
      </div>
    @endforeach
  </section>
</div>
@endsection
