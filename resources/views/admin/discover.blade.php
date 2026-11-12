@extends('layouts.admin')
@section('title', 'Découvrir – Admin')
@section('content')
@include('admin._nav', ['title' => 'Découvrir', 'lead' => 'Des vidéos de foot YouTube pour que les visiteurs comprennent FootPro dès leur arrivée : exercices, gestes techniques, gardiens, arbitrage, santé… Seules les vidéos dont la chaîne autorise l’intégration sont gardées, lues avec le lecteur officiel de YouTube, avec le nom de la chaîne.'])

@unless ($ready)
  <p class="note">Ajoute <code>YOUTUBE_API_KEY</code> dans le fichier <code>.env</code> (clé gratuite sur console.cloud.google.com, « YouTube Data API v3 »), puis clique « Ajouter des vidéos ».</p>
@endunless

<div class="kpis">
  <div class="kpi"><span>Vidéos visibles</span><b>{{ number_format($total, 0, ',', ' ') }}</b><small>objectif {{ number_format($values['disc_max'], 0, ',', ' ') }}</small></div>
  <div class="kpi"><span>Cachées</span><b>{{ $hidden }}</b><small>par toi</small></div>
  <div class="kpi"><span>Lectures</span><b>{{ number_format($plays, 0, ',', ' ') }}</b><small>sur FootPro</small></div>
  <div class="kpi"><span>Dernier ajout</span><b style="font-size:18px">{{ $last ? \Illuminate\Support\Carbon::parse($last['at'])->format('d/m H:i') : '–' }}</b><small>{{ $last ? $last['added'].' vidéo(s)' : 'jamais' }}</small></div>
</div>

<div class="adm-grid">
  <section class="panel">
    <h2>Ajouter des vidéos</h2>
    <p class="meta">L’ajout se fait tout seul chaque nuit jusqu’à l’objectif. Chaque clic ajoute jusqu’à 50 vidéos par thème (le quota gratuit de YouTube permet environ 4 000 vidéos par jour).</p>
    @if ($last && $last['error'])<p class="note">Dernier message de YouTube : {{ $last['error'] }}</p>@endif
    <form method="POST" action="{{ route('admin.discover.import') }}" class="formbar">@csrf<button class="btn" type="submit" @disabled(! $ready)>Ajouter des vidéos maintenant</button>
      <a class="btn ghost" href="{{ route('discover') }}" target="_blank" rel="noopener">Voir la page Découvrir</a></form>
  </section>
  @include('admin._settings', ['group' => 'decouvrir', 'title' => 'Réglages'])
</div>

<section class="panel">
  <h2>Vidéos</h2>
  <form method="GET" class="formbar" style="margin-bottom:10px"><input name="q" value="{{ $q }}" placeholder="Titre ou chaîne" aria-label="Chercher"><button class="btn ghost sm" type="submit">Chercher</button></form>
  @if ($rows->isEmpty())
    <p class="meta">Aucune vidéo pour l’instant.</p>
  @else
    <div class="admin-tbl"><table>
      <thead><tr><th>Vidéo</th><th>Chaîne</th><th>Thème</th><th class="num">Durée</th><th class="num">Lectures</th><th></th></tr></thead>
      <tbody>
      @foreach ($rows as $v)
        <tr class="{{ $v->hidden ? 'muted' : '' }}">
          <td><a href="https://www.youtube.com/watch?v={{ $v->youtube_id }}" target="_blank" rel="noopener"><b class="mname">{{ \Illuminate\Support\Str::limit($v->title, 70) }}</b></a></td>
          <td>{{ $v->channel }}</td>
          <td><span class="meta">{{ $v->topic }}</span></td>
          <td class="num">{{ \App\Terrain\Growth\Discover::duration((int) $v->duration) }}</td>
          <td class="num">{{ $v->plays }}</td>
          <td><div class="actbtns">
            <form method="POST" action="{{ route('admin.discover.action', $v->id) }}">@csrf<button class="btn ghost sm" type="submit">{{ $v->hidden ? 'Remettre' : 'Cacher' }}</button></form>
            <form method="POST" action="{{ route('admin.discover.action', $v->id) }}">@csrf<input type="hidden" name="action" value="supprimer"><button class="btn ghost sm" type="submit">Retirer</button></form>
          </div></td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
    <div class="pager">{{ $rows->links('pagination::simple-default') }}</div>
  @endif
</section>
@endsection
