@extends('layouts.admin')
@section('title', 'Publicités – Admin')
@section('content')
@include('admin._nav', ['title' => 'Publicités', 'lead' => 'Vidéos de pub glissées dans le fil et avant les matchs en direct. Les membres « Sans pub », Premium et Structure Pro n’en voient aucune.'])

@php
  $f = $edit;
  $fv = fn ($k, $d = '') => old($k, $f->{$k} ?? $d);
  $fc = $f ? (json_decode((string) $f->countries, true) ?: []) : [];
  $ctr = fn ($i, $c) => $i ? str_replace('.', ',', (string) round($c / $i * 100, 1)).' %' : '–';
  $days = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->toDateString());
  $max = max(1, (int) collect($daily)->max());
@endphp

<div class="kpis">
  <div class="kpi"><span>Pubs actives</span><b>{{ $totals['active'] }}</b><small>sur {{ $rows->count() }} au total</small></div>
  <div class="kpi"><span>Vues</span><b>{{ number_format($totals['imp'], 0, ',', ' ') }}</b><small>depuis le début</small></div>
  <div class="kpi"><span>Clics</span><b>{{ number_format($totals['clk'], 0, ',', ' ') }}</b><small>taux de clic {{ $ctr($totals['imp'], $totals['clk']) }}</small></div>
  <div class="kpi"><span>Membres « Sans pub »</span><b>{{ $totals['adfree'] }}</b><small>option payante en cours</small></div>
  <div class="kpi accent"><span>Facturé aux annonceurs</span><b>{{ number_format($totals['revenue'], 0, ',', ' ') }}</b><small>GNF, montants saisis ci-dessous</small></div>
</div>

<div class="adm-grid">
  <section class="panel">
    <h2>Vues des pubs sur 30 jours</h2>
    <svg class="bars" viewBox="0 0 300 90" role="img" aria-label="Vues par jour">
      @foreach ($days as $i => $d)
        @php
          $n = (int) ($daily[$d] ?? 0);
          $h = $n ? max(2, round($n / $max * 70)) : 1;
        @endphp
        <rect class="{{ $n ? 'on' : 'off' }}" x="{{ $i * 10 }}" y="{{ 72 - $h }}" width="7" height="{{ $h }}" rx="1.5"><title>{{ $d }} : {{ $n }}</title></rect>
      @endforeach
      <text x="0" y="88">{{ \Illuminate\Support\Carbon::parse($days->first())->format('d/m') }}</text><text x="300" y="88" text-anchor="end">aujourd’hui</text>
    </svg>
  </section>
  @include('admin._settings', ['group' => 'pub', 'title' => 'Réglages'])
</div>

<section class="panel" id="formulaire">
  <h2>{{ $f ? 'Modifier « '.$f->title.' »' : 'Ajouter une pub' }}</h2>
  <form method="POST" action="{{ route('admin.ads.save') }}" enctype="multipart/form-data" id="adform" class="adform">@csrf
    @if ($f)<input type="hidden" name="id" value="{{ $f->id }}">@endif
    <input type="hidden" name="upload_id" id="upload_id"><input type="hidden" name="file_name" id="file_name">
    <div class="formgrid">
      <label>Titre de la pub<input name="title" value="{{ $fv('title') }}" maxlength="120" required placeholder="ex. Nouvelle collection maillots"></label>
      <label>Annonceur<input name="advertiser" value="{{ $fv('advertiser') }}" maxlength="120" required placeholder="ex. Fabara"></label>
      <label class="wide">Vidéo de la pub (MP4, format vertical conseillé)
        <input type="file" id="adfile" accept="video/mp4,video/quicktime,video/webm">
        <small>@if ($f && $f->video_path)Vidéo actuelle gardée si tu n’en choisis pas d’autre.@else Ou colle un lien https://…mp4 ci-dessous.@endif</small></label>
      <label class="wide">Ou lien de la vidéo<input name="video_url" type="url" value="{{ $fv('video_url') }}" placeholder="https://…/pub.mp4"></label>
      <label>Image d’attente (facultatif)<input type="file" name="poster" accept="image/*"></label>
      <label>Lien ouvert au clic<input name="link" type="url" value="{{ $fv('link') }}" placeholder="https://"></label>
      <label>Texte du bouton<input name="cta" value="{{ $fv('cta', 'En savoir plus') }}" maxlength="40"></label>
      <label>Sport ciblé<select name="sport"><option value="">Tous les sports</option>@foreach ($sports as $s)<option @selected($fv('sport') === $s)>{{ $s }}</option>@endforeach</select></label>
      <label>Début<input type="date" name="starts_on" value="{{ $fv('starts_on') }}"></label>
      <label>Fin<input type="date" name="ends_on" value="{{ $fv('ends_on') }}"></label>
      <label>Vues maximum<input type="number" name="max_impressions" min="0" value="{{ $fv('max_impressions', 0) }}"><small>0 = sans limite</small></label>
      <label>Fréquence (1 à 10)<input type="number" name="weight" min="1" max="10" value="{{ $fv('weight', 1) }}"><small>Plus c’est haut, plus elle revient souvent</small></label>
      <label>Montant facturé<input type="number" name="price" min="0" value="{{ $fv('price', 0) }}"></label>
      <label>Monnaie<select name="currency">@foreach (['GNF', 'XOF', 'USD', 'EUR'] as $c)<option @selected($fv('currency', 'GNF') === $c)>{{ $c }}</option>@endforeach</select></label>
    </div>
    <fieldset class="countrypick"><legend>Pays où la pub est montrée <small>(aucun coché = tous)</small></legend>
      @foreach ($countries as $c)<label class="check"><input type="checkbox" name="countries[]" value="{{ $c }}" @checked(in_array($c, old('countries', $fc), true))> {{ $c }}</label>@endforeach
    </fieldset>
    <div class="upbar" id="upbar" hidden><div class="track"><i id="upfill"></i></div><span id="uptext"></span></div>
    <div class="formbar">
      <button class="btn" type="submit" id="adgo">{{ $f ? 'Enregistrer' : 'Ajouter la pub' }}</button>
      @if ($f)<a class="btn ghost" href="{{ route('admin.ads') }}">Annuler</a>@endif
    </div>
  </form>
</section>

<section class="panel">
  <h2>Toutes les pubs</h2>
  @if ($rows->isEmpty())
    <p class="meta">Aucune pub pour l’instant. Ajoute la première ci-dessus.</p>
  @else
    <div class="admin-tbl"><table>
      <thead><tr><th>Pub</th><th>Ciblage</th><th>Période</th><th class="num">Vues</th><th class="num">Clics</th><th class="num">Taux</th><th>État</th><th></th></tr></thead>
      <tbody>
      @foreach ($rows as $a)
        @php
          $cs = json_decode((string) $a->countries, true) ?: [];
          $ended = ($a->ends_on && $a->ends_on < now()->toDateString()) || ($a->max_impressions && $a->impressions >= $a->max_impressions);
        @endphp
        <tr>
          <td><b class="mname">{{ $a->title }}</b><span class="meta">{{ $a->advertiser }}@if ($a->price) · {{ number_format($a->price, 0, ',', ' ') }} {{ $a->currency }}@endif</span></td>
          <td><span class="meta">{{ $cs ? implode(', ', $cs) : 'Tous les pays' }}{{ $a->sport ? ' · '.$a->sport : '' }}</span></td>
          <td><span class="meta">{{ $a->starts_on ? \Illuminate\Support\Carbon::parse($a->starts_on)->format('d/m') : '…' }} → {{ $a->ends_on ? \Illuminate\Support\Carbon::parse($a->ends_on)->format('d/m') : '…' }}</span></td>
          <td class="num">{{ number_format($a->impressions, 0, ',', ' ') }}@if ($a->max_impressions)<span class="meta">/ {{ number_format($a->max_impressions, 0, ',', ' ') }}</span>@endif</td>
          <td class="num">{{ number_format($a->clicks, 0, ',', ' ') }}</td>
          <td class="num">{{ $ctr($a->impressions, $a->clicks) }}</td>
          <td>@if (! $a->active)<span class="pill">En pause</span>@elseif ($ended)<span class="pill warn">Terminée</span>@else<span class="pill ok">Active</span>@endif</td>
          <td><div class="actbtns">
            <a class="btn ghost sm" href="{{ route('admin.ads', ['modifier' => $a->id]) }}#formulaire">Modifier</a>
            <form method="POST" action="{{ route('admin.ads.action', $a->id) }}">@csrf<button class="btn ghost sm" type="submit">{{ $a->active ? 'Pause' : 'Réactiver' }}</button></form>
            <form method="POST" action="{{ route('admin.ads.action', $a->id) }}" onsubmit="return confirm('Supprimer cette pub et ses statistiques ?')">@csrf<input type="hidden" name="action" value="supprimer"><button class="btn ghost sm" type="submit">Supprimer</button></form>
          </div></td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
  @endif
</section>
@endsection

@push('scripts')
<script>
(function () {
  // La vidéo part en morceaux de 4 Mo avant l'envoi du formulaire (aucune limite de taille de PHP à changer)
  var form = document.getElementById('adform'), input = document.getElementById('adfile'), CHUNK = {{ $chunk }};
  var bar = document.getElementById('upbar'), fill = document.getElementById('upfill'), txt = document.getElementById('uptext'), go = document.getElementById('adgo');
  var csrf = form.querySelector('[name=_token]').value, url = @json(route('admin.ads.chunk')), sent = false;
  function rid() { var s = '', c = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; for (var i = 0; i < 32; i++) s += c[Math.floor(Math.random() * c.length)]; return s; }
  form.addEventListener('submit', function (e) {
    var file = input.files[0]; if (!file || sent) return;
    e.preventDefault(); go.disabled = true; bar.hidden = false;
    var id = rid(), total = Math.ceil(file.size / CHUNK), i = 0;
    (function next() {
      if (i >= total) { document.getElementById('upload_id').value = id; document.getElementById('file_name').value = file.name; sent = true; form.submit(); return; }
      fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Upload-Id': id, 'X-Chunk-Index': i, 'X-Chunk-Total': total, 'X-File-Size': file.size, 'Content-Type': 'application/octet-stream' }, body: file.slice(i * CHUNK, (i + 1) * CHUNK) })
        .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || 'Envoi impossible'); return d; }); })
        .then(function () { i++; fill.style.width = Math.round(i / total * 100) + '%'; txt.textContent = 'Envoi de la vidéo… ' + Math.round(i / total * 100) + ' %'; next(); })
        .catch(function (err) { txt.textContent = err.message; go.disabled = false; });
    })();
  });
})();
</script>
@endpush
