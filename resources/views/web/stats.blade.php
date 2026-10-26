@extends('layouts.web')

@section('title', __('Mes statistiques').' – '.config('terrain.app_name'))

@php
  $days = $s['days']; $max = max(1, max($days)); $w = 600; $h = 150; $n = count($days); $bw = $w / $n;
  $countryNames = collect(\App\Terrain\Region::COUNTRIES)->mapWithKeys(fn ($c, $name) => [$c[0] => $name])->all() + ['FR' => 'France', 'US' => 'États-Unis', 'BE' => 'Belgique', 'ES' => 'Espagne'];
  $cmax = max(array_merge([1], array_values($s['countries'])));
@endphp

@section('content')
<div class="page gut">
  <div class="row" style="justify-content:space-between;align-items:center">
    <h1>{{ __('Mes statistiques') }}</h1>
    <a class="btn ghost sm" href="{{ route('premium', 'boost') }}">{{ __('Booster mon profil') }}</a>
  </div>
  @if (count($badges))<div class="badges">@foreach ($badges as [$ico, $label, $why])<span class="bdg" title="{{ $why }}"><i>{{ $ico }}</i>{{ $label }}</span>@endforeach</div>@endif

  <div class="grid4" style="margin-top:14px">
    <div class="card kpi"><b>{{ $s['total30'] }}</b><span class="meta">{{ __('vues du profil (30 jours)') }}</span></div>
    <div class="card kpi"><b>{{ $s['clubs'] }}</b><span class="meta">{{ __('recruteurs ont vu ton profil') }}</span></div>
    <div class="card kpi"><b>{{ number_format(array_sum(array_column($a['videos'], 'views')) + array_sum(array_column($s['videos'], 'site_views')), 0, ',', ' ') }}</b><span class="meta">{{ __('vues des vidéos') }}</span></div>
    <div class="card kpi"><b>{{ $s['followers'] }}</b><span class="meta">{{ __('abonnés') }}</span></div>
  </div>

  <div class="section-label">{{ __('Vues du profil, 30 derniers jours') }}</div>
  <svg viewBox="0 0 {{ $w }} {{ $h + 20 }}" style="width:100%;height:auto;display:block" role="img" aria-label="{{ __('Vues du profil par jour') }}">
    @foreach (array_values($days) as $i => $v)
      @php
        $bh = $v / $max * ($h - 10);
      @endphp
      <rect x="{{ round($i * $bw + 2, 1) }}" y="{{ round($h - $bh, 1) }}" width="{{ round($bw - 4, 1) }}" height="{{ round(max($bh, 1), 1) }}" rx="2" fill="var(--pitch)" opacity="{{ $v ? .85 : .2 }}"><title>{{ array_keys($days)[$i] }} : {{ $v }}</title></rect>
    @endforeach
    <text x="0" y="{{ $h + 16 }}" font-size="11" fill="var(--muted)">{{ \Illuminate\Support\Carbon::parse(array_key_first($days))->format('d/m') }}</text>
    <text x="{{ $w }}" y="{{ $h + 16 }}" font-size="11" fill="var(--muted)" text-anchor="end">{{ __("Aujourd'hui") }}</text>
  </svg>
  @if (! $s['all'])<p class="meta">{{ __('Pas encore de vue. Partage ton profil sur WhatsApp et participe au défi de la semaine pour te faire remarquer.') }}</p>@endif

  <div class="cols" style="margin-top:8px">
    <div>
      <div class="section-label">{{ __('Recruteurs qui ont vu ton profil') }}</div>
      <div class="list">
        @forelse ($s['recruiters'] as $r)
          <div class="li"><x-av :name="$r->name ?: 'Recruteur'" sport="Football" />
            <span class="grow"><span class="t">{{ $r->name ?: __('Recruteur') }}</span><span class="s">{{ $r->n }} {{ $r->n > 1 ? __('visites') : __('visite') }} · {{ \Illuminate\Support\Carbon::parse($r->last)->diffForHumans() }}</span></span></div>
        @empty
          <p class="meta">{{ __('Aucun recruteur pour l’instant.') }}</p>
        @endforelse
      </div>
      <div class="section-label">{{ __('Mes vidéos les plus vues') }}</div>
      <div class="list">
        @foreach (array_slice($s['videos'], 0, 6) as $v)
          <a class="li" href="{{ route('feed', ['v' => $v['id']]) }}"><span class="grow"><span class="t">{{ $v['title'] }}</span>
            <span class="s">{{ number_format($v['views'] + $v['site_views'], 0, ',', ' ') }} {{ __('vues') }} · {{ $v['likes'] }} ❤</span></span></a>
        @endforeach
      </div>
    </div>
    <aside>
      <div class="section-label">{{ __('D’où viennent les visites') }}</div>
      @forelse ($s['countries'] as $c => $n)
        <div class="hbar"><span>{{ $countryNames[$c] ?? $c }}</span><i style="width:{{ round($n / $cmax * 100) }}%"></i><b>{{ $n }}</b></div>
      @empty
        <p class="meta">{{ __('Les pays s’affichent quand le site est en ligne (derrière Cloudflare).') }}</p>
      @endforelse
      <div class="card" style="margin-top:14px">
        <b>{{ __('Augmente tes vues') }}</b>
        <a href="{{ route('athletes.card', $a['id']) }}">🪪 {{ __('Partage ta carte de joueur') }}</a>
        <a href="{{ route('challenges') }}">🏆 {{ __('Participe au défi de la semaine') }}</a>
        <a href="{{ route('events') }}">📅 {{ __('Inscris-toi à une détection') }}</a>
        <a href="{{ route('verification') }}">✓ {{ __('Fais vérifier ton profil') }}</a>
      </div>
    </aside>
  </div>
</div>
@endsection
