@extends('layouts.admin')
@section('title', 'Contenus – Admin')
@section('content')
@include('admin._nav', ['title' => 'Contenus', 'lead' => 'Toutes les vidéos, offres, détections et commentaires du site. Tu peux les ouvrir et les supprimer.'])

@php
  $tabs = ['videos' => 'Vidéos', 'offres' => 'Offres', 'detections' => 'Détections', 'commentaires' => 'Commentaires'];
@endphp
<nav class="tabs" aria-label="Contenus">
  @foreach ($tabs as $k => $l)<a href="{{ route('admin.content', ['onglet' => $k]) }}" @if ($tab === $k) aria-current="page" @endif>{{ $l }} <b>{{ $counts[$k] }}</b></a>@endforeach
</nav>
<form class="adm-filters" method="GET" action="{{ route('admin.content') }}">
  <input type="hidden" name="onglet" value="{{ $tab }}">
  <input type="search" name="q" value="{{ $q }}" placeholder="Rechercher…" aria-label="Rechercher">
  <button class="btn" type="submit">Rechercher</button>
</form>

@if ($rows->isEmpty())
  <div class="empty"><b>Rien ici.</b><span>{{ $q !== '' ? 'Aucun résultat pour « '.$q.' ».' : 'Les contenus publiés par les membres apparaîtront ici.' }}</span></div>
@else
  <div class="admin-tbl"><table>
    @if ($tab === 'videos')
      <thead><tr><th>Vidéo</th><th>Sportif</th><th>Publiée</th><th></th></tr></thead><tbody>
      @foreach ($rows as $r)
        <tr><td><b class="mname">{{ $r->title }}</b><span class="meta">{{ $r->id }}</span></td>
          <td>@if ($r->account_id)<a href="{{ route('admin.member', $r->account_id) }}">{{ $r->athlete_name ?? '–' }}</a>@else{{ $r->athlete_name ?? '–' }}@endif</td>
          <td><span class="meta">{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}</span></td>
          <td><div class="actbtns"><a class="btn ghost sm" href="{{ route('feed', ['v' => $r->id]) }}" target="_blank" rel="noopener">Voir</a>
            <form method="POST" action="{{ route('admin.videos.delete', $r->id) }}" onsubmit="return confirm('Supprimer définitivement cette vidéo ?')">@csrf<button class="btn ghost sm" type="submit">Supprimer</button></form></div></td></tr>
      @endforeach
    @elseif ($tab === 'offres')
      <thead><tr><th>Offre</th><th>Structure</th><th>Lieu · date limite</th><th></th></tr></thead><tbody>
      @foreach ($rows as $r)
        <tr><td><b class="mname">{{ $r->title }}</b><span class="meta">{{ $r->sport }} · {{ $r->criteria }}</span></td>
          <td>@if ($r->owner_key)<a href="{{ route('admin.member', $r->owner_key) }}">{{ $r->organization }}</a>@else{{ $r->organization }}@endif</td>
          <td><span class="meta">{{ $r->place }} · {{ $r->deadline }}</span></td>
          <td><form method="POST" action="{{ route('admin.content.delete', ['offre', $r->id]) }}" onsubmit="return confirm('Supprimer cette offre et ses candidatures ?')">@csrf<button class="btn ghost sm" type="submit">Supprimer</button></form></td></tr>
      @endforeach
    @elseif ($tab === 'detections')
      <thead><tr><th>Détection</th><th>Date</th><th>Lieu</th><th></th></tr></thead><tbody>
      @foreach ($rows as $r)
        <tr><td><b class="mname">{{ $r->title }}</b><span class="meta">{{ $r->sport }}{{ $r->demo ? ' · exemple' : '' }}</span></td>
          <td>{{ $r->date }} {{ $r->time }}</td><td><span class="meta">{{ $r->place }}, {{ $r->city }}</span></td>
          <td><div class="actbtns"><a class="btn ghost sm" href="{{ route('events.show', $r->id) }}" target="_blank" rel="noopener">Voir</a>
            <form method="POST" action="{{ route('admin.content.delete', ['detection', $r->id]) }}" onsubmit="return confirm('Supprimer cette détection et ses inscriptions ?')">@csrf<button class="btn ghost sm" type="submit">Supprimer</button></form></div></td></tr>
      @endforeach
    @else
      <thead><tr><th>Commentaire</th><th>Auteur</th><th>Date</th><th></th></tr></thead><tbody>
      @foreach ($rows as $r)
        <tr><td><q>{{ \Illuminate\Support\Str::limit($r->body, 160) }}</q><span class="meta"><a href="{{ route('feed', ['v' => $r->video_id, 'commentaires' => 1]) }}" target="_blank" rel="noopener">vidéo {{ $r->video_id }}</a></span></td>
          <td>@if ($r->user_key)<a href="{{ route('admin.member', $r->user_key) }}">{{ $r->author_name }}</a>@else{{ $r->author_name }}@endif</td>
          <td><span class="meta">{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}</span></td>
          <td><form method="POST" action="{{ route('admin.content.delete', ['commentaire', $r->id]) }}" onsubmit="return confirm('Supprimer ce commentaire et ses réponses ?')">@csrf<button class="btn ghost sm" type="submit">Supprimer</button></form></td></tr>
      @endforeach
    @endif
    </tbody>
  </table></div>
@endif
@endsection
