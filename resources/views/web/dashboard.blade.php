@extends('layouts.web')

@section('title', 'Mon profil – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  @if (! $a)
    <h1>Mon profil</h1>
    <p>Ton profil de sportif n'est pas encore créé.</p>
    <a class="btn" href="{{ route('register') }}">Créer mon profil</a>
  @else
    @php
      $pct = count($checklist) ? round(count(array_filter($checklist)) / count($checklist) * 100) : 0;
      $max = max(1, max($views)) * 1.1; $w = 320; $h = 80; $step = $w / (count($views) - 1);
      $pts = []; foreach ($views as $i => $v) { $pts[] = round($i * $step, 1).','.round($h - 6 - $v / $max * ($h - 14), 1); }
      $totalViews = array_sum(array_column($a['videos'], 'views'));
    @endphp
    <div class="cols">
      <div>
        <div style="display:flex;gap:16px;align-items:center">
          <x-av :name="$a['name']" :sport="$a['sport']" lg />
          <div><h1 style="margin:0">{{ $a['name'] }}</h1><div class="meta">{{ $a['sport'] }} · {{ \Illuminate\Support\Arr::first($a['fields']) }}</div></div>
        </div>
        <div class="row" style="margin-top:14px">
          <a class="btn" href="{{ route('publish') }}">{{ __('Publier une vidéo') }}</a>
          <a class="btn ghost" href="{{ route('athletes.show', $a['id']) }}">{{ __('Mon profil public') }}</a>
          <a class="btn ghost" href="{{ route('premium', 'boost') }}">{{ __('Booster mon profil') }}</a>
        </div>
        <div class="quick">
          <a href="{{ route('stats') }}"><x-ico name="stats" /><span>Statistiques</span></a>
          <a href="{{ route('athletes.card', $a['id']) }}"><x-ico name="card" /><span>Carte de joueur</span></a>
          <a href="{{ route('athletes.cv', $a['id']) }}"><x-ico name="doc" /><span>CV sportif</span></a>
          <a href="{{ route('challenges') }}"><x-ico name="trophy" /><span>Défi de la semaine</span></a>
          <a href="{{ route('events') }}"><x-ico name="calendar" /><span>Détections</span></a>
          <a href="{{ route('referral') }}"><x-ico name="gift" /><span>Inviter des amis</span></a>
          <a href="{{ route('video.services') }}"><x-ico name="film" /><span>{{ __('Montage vidéo') }}</span></a>
          @unless ($a['verified'])<a href="{{ route('verification') }}"><x-ico name="check" /><span>Faire vérifier</span></a>@endunless
        </div>

        <div class="grid3" style="margin-top:18px">
          <div class="card" style="gap:2px"><b style="font-family:var(--f-display);font-size:34px;line-height:1">{{ array_sum($views) }}</b><span class="meta">vues du profil (7 jours)</span></div>
          <div class="card" style="gap:2px"><b style="font-family:var(--f-display);font-size:34px;line-height:1">{{ number_format($totalViews, 0, ',', ' ') }}</b><span class="meta">vues des vidéos</span></div>
          <div class="card" style="gap:2px"><b style="font-family:var(--f-display);font-size:34px;line-height:1">{{ count($a['videos']) }}</b><span class="meta">compilations</span></div>
        </div>

        <div class="section-label">Vues du profil, 7 derniers jours · <a href="{{ route('stats') }}">détails</a></div>
        <svg viewBox="0 0 {{ $w }} {{ $h + 18 }}" style="width:100%;height:auto;display:block" role="img" aria-label="Vues du profil sur 7 jours">
          <polygon points="0,{{ $h }} {{ implode(' ', $pts) }} {{ $w }},{{ $h }}" fill="var(--pitch)" opacity=".14"/>
          <polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="var(--pitch)" stroke-width="2.4"/>
          @foreach (range(6, 0) as $i => $back)
            @php
              $d = now()->subDays($back)->translatedFormat('D');
            @endphp
            <text x="{{ min(max($i * $step, 12), $w - 12) }}" y="{{ $h + 15 }}" font-size="10" text-anchor="middle" fill="var(--muted)">{{ $d }}</text>
          @endforeach
        </svg>

        <div class="section-label">Mes compilations</div>
        @if (count($a['videos']))
          <div class="thumbs">@foreach ($a['videos'] as $v)@include('web.partials.thumb', ['a' => $a, 'v' => $v])@endforeach</div>
        @else
          <p class="meta">Pas encore de vidéo. <a href="{{ route('publish') }}">Publie ta première compilation</a>.</p>
        @endif
      </div>

      <aside>
        <div class="card">
          <b>Force du profil : {{ $pct }} %</b>
          <div class="meter"><i style="width:{{ $pct }}%"></i></div>
          @foreach ($checklist as $label => $done)
            <div class="check" style="{{ $done ? 'color:var(--muted);text-decoration:line-through' : '' }}">{{ $done ? '✓' : '○' }} {{ $label }}</div>
          @endforeach
        </div>
        <div class="card" style="margin-top:12px">
          <a href="{{ route('messages') }}">Mes messages</a>
          <a href="{{ route('offers') }}">Offres et candidatures</a>
          @if ($realAccounts ?? false)<a href="{{ route('profile.edit') }}">{{ __('Modifier mon profil') }}</a>@endif
          <a href="{{ route('payments.history') }}">{{ __('Mes paiements') }}</a>
          <a href="{{ route('settings') }}">Paramètres et confidentialité</a>
          <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn ghost" type="submit">Se déconnecter</button></form>
        </div>
      </aside>
    </div>
  @endif
</div>
@endsection
