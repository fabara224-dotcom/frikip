@extends('layouts.admin')
@section('title', 'Défis – Admin')
@section('content')
@include('admin._nav', ['title' => 'Défis', 'lead' => 'Le défi de la semaine s’ouvre tout seul chaque lundi. Tu peux ajouter des défis spéciaux avec un lot et un sponsor, et retirer une participation qui triche.'])

<div class="kpis">
  <div class="kpi"><span>Défis en cours</span><b>{{ $stats['open'] }}</b><small>visibles sur le site</small></div>
  <div class="kpi"><span>Participations</span><b>{{ number_format($stats['entries'], 0, ',', ' ') }}</b><small>sur 30 jours</small></div>
  <div class="kpi"><span>Votes</span><b>{{ number_format($stats['votes'], 0, ',', ' ') }}</b><small>sur 30 jours</small></div>
</div>

<div class="adm-grid">
  <section class="panel">
    <h2>Créer un défi spécial</h2>
    <form method="POST" action="{{ route('admin.challenges.create') }}" class="adform">@csrf
      <input type="hidden" name="mode" value="special">
      <div class="formgrid">
        <label class="wide">Titre<input name="title" required maxlength="120" placeholder="ex. Plus beau coup franc – spécial Fabara"></label>
        <label class="wide">Ce qu’il faut montrer<textarea name="description" rows="2" maxlength="500" placeholder="ex. Ton plus beau coup franc filmé à l’entraînement ou en match."></textarea></label>
        <label>Sport<select name="sport"><option value="">Tous les sports</option>@foreach ($sports as $s)<option>{{ $s }}</option>@endforeach</select></label>
        <label>Pays<select name="country"><option value="">Toute la région</option>@foreach ($countries as $c)<option>{{ $c }}</option>@endforeach</select></label>
        <label class="wide">Lot<input name="prize" maxlength="160" placeholder="ex. Un maillot personnalisé Fabara"></label>
        <label>Sponsor<input name="sponsor" maxlength="120" placeholder="ex. Fabara"></label>
        <label>Lien du sponsor<input name="sponsor_url" type="url" placeholder="https://"></label>
        <label>Début<input name="starts_on" type="date" value="{{ now()->toDateString() }}"></label>
        <label>Durée (jours)<input name="days" type="number" min="1" max="60" value="7" required></label>
      </div>
      <div class="formbar"><button class="btn" type="submit">Ouvrir le défi</button></div>
    </form>
    <h2 style="margin-top:20px">Ou ouvrir un défi de la semaine tout de suite</h2>
    <form method="POST" action="{{ route('admin.challenges.create') }}" class="formbar">@csrf
      <select name="theme" aria-label="Thème" style="width:auto">@foreach ($themes as $k => $t)<option value="{{ $k }}">{{ $t[0] }}</option>@endforeach</select>
      <input name="days" type="number" min="1" max="30" value="7" aria-label="Durée en jours" style="width:90px">
      <button class="btn ghost" type="submit">Ouvrir</button>
    </form>
  </section>
  @include('admin._settings', ['group' => 'defis', 'title' => 'Récompenses et votes'])
</div>

<section class="panel">
  <h2>Défis</h2>
  <div class="mqueue">
    @foreach ($rows as $c)
      <div class="mcard" style="grid-template-columns:1fr auto">
        <div class="mbody">
          <span class="mkind">{{ ($c->kind ?? 'semaine') === 'special' ? 'Défi spécial' : 'Défi de la semaine' }}{{ $c->sponsor ? ' · '.$c->sponsor : '' }}</span>
          <b>{{ $c->title }}</b>
          <small>{{ \Illuminate\Support\Carbon::parse($c->starts_on)->format('d/m') }} → {{ \Illuminate\Support\Carbon::parse($c->ends_on)->format('d/m/Y') }}{{ $c->sport ? ' · '.$c->sport : '' }}{{ $c->country ? ' · '.$c->country : '' }}{{ $c->prize ? ' · lot : '.$c->prize : '' }}</small>
          <span><span class="pill {{ $c->status === 'ouvert' ? 'ok' : '' }}">{{ $c->status === 'ouvert' ? 'En cours' : 'Terminé' }}</span></span>
          @if (! empty($entries[$c->id]) && count($entries[$c->id]))
            <details style="margin-top:6px"><summary>{{ count($entries[$c->id]) }} participation(s)</summary>
              @foreach ($entries[$c->id] as $i => $e)
                <div class="line {{ $e->excluded ? 'muted' : '' }}"><span><b>{{ $i + 1 }}. {{ $names[$e->athlete_id] ?? $e->athlete_id }}</b><small>{{ $e->votes }} vote(s){{ $e->excluded ? ' · retirée' : '' }}</small></span>
                  <span class="actbtns"><a class="btn ghost sm" href="{{ route('feed', ['v' => $e->video_id]) }}" target="_blank" rel="noopener">Vidéo</a>
                    <form method="POST" action="{{ route('admin.challenges.exclude', $e->id) }}">@csrf<button class="btn ghost sm" type="submit">{{ $e->excluded ? 'Remettre' : 'Retirer' }}</button></form></span></div>
              @endforeach
            </details>
          @endif
        </div>
        <div class="mact">
          <a class="btn ghost sm" href="{{ route('challenges.show', $c->id) }}" target="_blank" rel="noopener">Voir</a>
          @if ($c->status === 'ouvert')<form method="POST" action="{{ route('admin.challenges.close', $c->id) }}" onsubmit="return confirm('Clôturer maintenant et annoncer le gagnant ?')">@csrf<button class="btn sm" type="submit">Clôturer et récompenser</button></form>@endif
        </div>
      </div>
    @endforeach
  </div>
</section>
@endsection
