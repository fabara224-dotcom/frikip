@extends('layouts.admin')
@section('title', 'Vue d’ensemble – Admin')
@section('content')
@php
  $max = max(1, max($signups ?: [0]));
  $n = count($signups);
  $cmax = max(1, max($byCountry ?: [0]));
  $fmt = fn ($v) => number_format($v, 0, ',', ' ');
@endphp
@include('admin._nav', ['title' => 'Vue d’ensemble', 'lead' => now()->translatedFormat('l j F Y').' · '.$fmt($kpi['profiles']).' profils visibles sur le site'])

<div class="kpis">
  <a class="kpi" href="{{ route('admin.members') }}"><span>Membres inscrits</span><b>{{ $fmt($kpi['members']) }}</b><small>+{{ $fmt($kpi['new7']) }} en 7 jours · {{ $fmt($kpi['athletes']) }} sportifs, {{ $fmt($kpi['recruiters']) }} recruteurs</small></a>
  <a class="kpi" href="{{ route('admin.members', ['sort' => 'actif']) }}"><span>Actifs cette semaine</span><b>{{ $fmt($kpi['active7']) }}</b><small>membres venus sur le site en 7 jours</small></a>
  <div class="kpi"><span>Vidéos publiées</span><b>{{ $fmt($kpi['videos']) }}</b><small>{{ $fmt($kpi['videosMonth']) }} ce mois-ci</small></div>
  <a class="kpi" href="{{ route('admin.members', ['state' => 'payant']) }}"><span>Clients payants</span><b>{{ $fmt($kpi['paying']) }}</b><small>Premium, Structure Pro ou Boost en cours</small></a>
  <a class="kpi accent" href="{{ route('admin.payments') }}"><span>Encaissé ce mois</span>
    @forelse ($revenue as $r)<b>{{ \App\Terrain\Region::format($r->total, $r->currency) }}</b>@empty<b>0</b>@endforelse
    <small>@if ($revenue){{ collect($revenue)->sum('n') }} paiement(s) réel(s)@else aucun paiement réel @endif @if ($simulated) · {{ $simulated }} simulé(s) non comptés @endif</small></a>
</div>

@php
  $gb = $health['storage'] / 1073741824;
  $mins = intdiv($health['watch'], 60); $secs = $health['watch'] % 60;
  $types = ['athlete' => 'joueurs et acteurs du foot', 'fan' => 'supporters', 'recruiter' => 'clubs, recruteurs et agents'];
@endphp
<div class="kpis">
  <div class="kpi"><span>Visionnages (30 jours)</span><b>{{ $fmt($health['views30']) }}</b><small>vues de plus de 2 secondes</small></div>
  <div class="kpi"><span>Durée moyenne regardée</span><b>{{ $health['watch'] ? ($mins ? $mins.' min '.$secs.' s' : $secs.' s') : '–' }}</b><small>par vue, sur 30 jours</small></div>
  <div class="kpi"><span>Rétention</span><b>{{ $health['retention'] !== null ? $health['retention'].' %' : '–' }}</b><small>@if ($health['cohort']) inscrits il y a 1 à 2 mois revenus cette semaine ({{ $health['cohort'] }} membres)@else pas encore assez d’ancienneté @endif</small></div>
  <a class="kpi" href="{{ route('admin.moderation') }}"><span>Signalements ouverts</span><b>{{ $health['reports'] }}</b><small>à examiner</small></a>
  <div class="kpi"><span>Stockage utilisé</span><b>{{ $gb >= 1 ? number_format($gb, 1, ',', ' ').' Go' : number_format($health['storage'] / 1048576, 0, ',', ' ').' Mo' }}</b><small>vidéos et fichiers sur le serveur</small></div>
</div>
<p class="meta" style="margin:-6px 0 16px">@foreach ($types as $k => $l){{ $fmt((int) ($health['byType'][$k] ?? 0)) }} {{ $l }}@if (! $loop->last) · @endif @endforeach</p>

<div class="adm-grid">
  <section class="panel">
    <h2>À traiter</h2>
    <ul class="queues">
      @foreach ($queues as [$route, $label, $nb])
        <li><a href="{{ route($route) }}"><span>{{ $label }}</span><b class="{{ $nb ? 'hot' : '' }}">{{ $nb }}</b></a></li>
      @endforeach
    </ul>
    <p class="meta" style="margin:12px 0 0">{{ $sanctions }} compte(s) suspendu(s) ou bloqué(s) · {{ $autoBlocked }} contenu(s) arrêté(s) par l’algorithme en 7 jours</p>
  </section>

  <section class="panel">
    <h2>Inscriptions sur 30 jours</h2>
    <svg viewBox="0 0 600 170" class="bars" role="img" aria-label="Inscriptions par jour sur 30 jours">
      @foreach (array_values($signups) as $i => $v)
        @php
          $h = $v / $max * 130;
          $x = $i * (600 / $n);
        @endphp
        <rect x="{{ round($x + 2, 1) }}" y="{{ round(140 - max($h, 2), 1) }}" width="{{ round(600 / $n - 4, 1) }}" height="{{ round(max($h, 2), 1) }}" rx="3" class="{{ $v ? 'on' : 'off' }}"><title>{{ \Illuminate\Support\Carbon::parse(array_keys($signups)[$i])->format('d/m') }} : {{ $v }}</title></rect>
      @endforeach
      <text x="0" y="162">{{ \Illuminate\Support\Carbon::parse(array_key_first($signups))->format('d/m') }}</text>
      <text x="600" y="162" text-anchor="end">aujourd’hui</text>
    </svg>
  </section>

  <section class="panel">
    <h2>Membres par pays</h2>
    @forelse ($byCountry as $c => $nb)
      <a class="hbar2" href="{{ route('admin.members', ['country' => $c === 'Inconnu' ? '' : $c]) }}"><span>{{ $c }}</span><i><em style="width:{{ round($nb / $cmax * 100) }}%"></em></i><b>{{ $fmt($nb) }}</b></a>
    @empty
      <p class="meta">Aucun membre inscrit pour l’instant.</p>
    @endforelse
  </section>

  <section class="panel">
    <h2>Raccourcis</h2>
    <div class="shortcuts">
      <a href="{{ route('admin.algorithm') }}#mots">Modifier les mots interdits</a>
      <a href="{{ route('admin.algorithm') }}#fil">Régler ce que voient les visiteurs</a>
      <a href="{{ route('admin.members', ['state' => 'averti']) }}">Membres avec un avertissement</a>
      <a href="{{ route('admin.moderation', ['vue' => 'refus']) }}">Textes refusés automatiquement</a>
      <a href="{{ route('admin.journal') }}">Journal des actions</a>
    </div>
  </section>
</div>
@endsection
