@extends('layouts.admin')
@section('title', 'Signalements – '.config('terrain.app_name'))
@section('noindex', '1')
@section('content')
<div class="doc gut"><div class="wrap">
  @include('admin._nav', ['title' => 'Signalements et modération'])
  <nav class="filters" aria-label="Filtrer">
    @foreach (['nouveau' => 'À traiter', 'traite' => 'Traités', 'classe' => 'Classés sans suite', 'tous' => 'Tous'] as $k => $l)
      <a href="{{ route('admin.reports', ['statut' => $k]) }}" aria-current="{{ $status === $k ? 'true' : 'false' }}">{{ $l }} @if ($k !== 'tous')({{ $counts[$k] ?? 0 }})@endif</a>
    @endforeach
  </nav>
  <div class="tbl admin-tbl"><table>
    <thead><tr><th>N°</th><th>Reçu</th><th>Cible</th><th>Motif</th><th>Décision</th></tr></thead>
    <tbody>
    @forelse ($reports as $r)
      <tr>
        <td>{{ $r->id }}</td>
        <td style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m H:i') }}</td>
        <td><b>{{ ['video' => 'Vidéo', 'profile' => 'Profil', 'message' => 'Conversation', 'app' => 'Général'][$r->target_type] ?? $r->target_type }}</b><br><span class="meta">{{ $r->target_id ?: '–' }}</span>
          @if ($r->target_type === 'video' && $r->target_id)<br><a href="{{ route('feed', ['v' => $r->target_id]) }}" target="_blank">voir la vidéo</a>@endif
          @if (in_array($r->target_type, ['profile', 'message']) && $r->target_id)<br><a href="{{ route('athletes.show', $r->target_id) }}" target="_blank">voir le profil</a>@endif</td>
        <td>{{ $r->reason }}@if ($r->action)<br><span class="badge">{{ $r->action }}</span>@endif</td>
        <td>
          @if ($r->status === 'nouveau')
          <form method="POST" action="{{ route('admin.reports.handle', $r->id) }}" style="display:flex;flex-direction:column;gap:6px">@csrf
            <input name="target" value="{{ $r->target_id }}" placeholder="Id de la vidéo ou du compte" aria-label="Cible">
            <select name="action" aria-label="Décision">
              <option value="done">Traité (aucune sanction)</option>
              <option value="hide_video" @selected($r->target_type === 'video')>Masquer la vidéo</option>
              <option value="block_account" @selected(in_array($r->target_type, ['profile', 'message']))>Bloquer le compte</option>
              <option value="dismiss">Classer sans suite</option>
            </select>
            <input name="note" placeholder="Note interne" aria-label="Note">
            <button class="btn sm" type="submit">Appliquer</button>
          </form>
          @else
            <span class="meta">{{ $r->admin_note }}</span>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="5" class="meta">Aucun signalement.</td></tr>
    @endforelse
    </tbody></table></div>

  <h2 style="margin-top:28px">Décisions en cours</h2>
  <form method="POST" action="{{ route('admin.moderate') }}" class="row" style="gap:8px;flex-wrap:wrap;margin-bottom:12px">@csrf
    <select name="type" aria-label="Type"><option value="hide_video">Masquer une vidéo</option><option value="block_account">Bloquer un compte</option></select>
    <input name="target" placeholder="Id (ex. v12 ou a7)" required aria-label="Id">
    <input name="reason" placeholder="Motif" aria-label="Motif">
    <button class="btn ghost sm" type="submit">Appliquer</button>
  </form>
  <div class="tbl admin-tbl"><table>
    <thead><tr><th>Type</th><th>Cible</th><th>Motif</th><th>Depuis</th><th></th></tr></thead>
    <tbody>
    @forelse ($actions as $a)
      <tr><td>{{ $a->type === 'hide_video' ? 'Vidéo masquée' : 'Compte bloqué' }}</td><td>{{ $a->target_id }}</td><td class="meta">{{ $a->reason }}</td>
        <td>{{ \Illuminate\Support\Carbon::parse($a->created_at)->format('d/m/Y') }}</td>
        <td><form method="POST" action="{{ route('admin.lift', $a->id) }}">@csrf<button class="btn ghost sm" type="submit">Lever</button></form></td></tr>
    @empty
      <tr><td colspan="5" class="meta">Aucune vidéo masquée ni compte bloqué.</td></tr>
    @endforelse
    </tbody></table></div>
</div></div>
@endsection
