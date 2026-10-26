@extends('layouts.admin')
@section('title', 'Membres – Admin')
@section('content')
@include('admin._nav', ['title' => 'Membres', 'lead' => number_format($total, 0, ',', ' ').' membre(s) trouvé(s)'])

<form method="GET" action="{{ route('admin.members') }}" class="adm-filters">
  <input type="search" name="q" value="{{ $f['q'] }}" placeholder="Nom, e-mail, téléphone" aria-label="Rechercher un membre">
  <select name="country" aria-label="Pays"><option value="">Tous les pays</option>@foreach ($countries as $c)<option @selected($f['country'] === $c)>{{ $c }}</option>@endforeach</select>
  <select name="role" aria-label="Rôle"><option value="">Tous les rôles</option><option value="athlete" @selected($f['role'] === 'athlete')>Sportifs</option><option value="recruiter" @selected($f['role'] === 'recruiter')>Recruteurs</option></select>
  <select name="state" aria-label="État">
    @foreach (['' => 'Tous les états', 'actif' => 'Actifs', 'averti' => 'Avec avertissement', 'suspendu' => 'Suspendus', 'bloque' => 'Bloqués', 'payant' => 'Clients payants'] as $k => $l)<option value="{{ $k }}" @selected($f['state'] === $k)>{{ $l }}</option>@endforeach
  </select>
  <select name="sort" aria-label="Trier"><option value="recent" @selected($f['sort'] === 'recent')>Plus récents</option><option value="score" @selected($f['sort'] === 'score')>Meilleur score</option><option value="actif" @selected($f['sort'] === 'actif')>Dernière visite</option></select>
  <button class="btn sm" type="submit">Filtrer</button>
</form>

<div class="tbl admin-tbl"><table>
  <thead><tr><th>Membre</th><th>Pays</th><th>Rôle</th><th class="num">Score</th><th>État</th><th>Dernière visite</th></tr></thead>
  <tbody>
  @forelse ($rows as $m)
    @php
      $s = $sanctions[$m->user_key] ?? ($m->athlete_id ? ($sanctions[$m->athlete_id] ?? null) : null);
      $w = $strikes[$m->user_key] ?? 0;
    @endphp
    <tr>
      <td><a class="mname" href="{{ route('admin.member', $m->user_key) }}">{{ $m->name ?: $m->user_key }}</a><span class="meta">{{ $m->email ?: $m->phone }}</span></td>
      <td>{{ $m->country ?: '–' }}</td>
      <td>{{ $m->role === 'recruiter' ? 'Recruteur' : 'Sportif' }}</td>
      <td class="num">@if ($m->role === 'athlete')<span class="score" style="--v:{{ (int) $m->score }}">{{ round($m->score) }}</span>@else – @endif</td>
      <td>
        @if ($s && $s->type === 'block_account')<span class="pill danger">Bloqué</span>
        @elseif ($s)<span class="pill warn">Suspendu jusqu’au {{ \Illuminate\Support\Carbon::parse($s->until)->format('d/m') }}</span>
        @else<span class="pill ok">Actif</span>@endif
        @if ($w)<span class="pill warn">{{ $w }} avert.</span>@endif
        @if (($m->premium_until && strtotime($m->premium_until) > time()) || ($m->structure_until ?? null) && strtotime($m->structure_until) > time())<span class="pill pro">Payant</span>@endif
      </td>
      <td class="meta">{{ \App\Http\Controllers\AdminSupervisionController::since($m->last_seen_at) }}</td>
    </tr>
  @empty
    <tr><td colspan="6" class="meta">Aucun membre ne correspond à ces filtres.</td></tr>
  @endforelse
  </tbody></table></div>

@if ($pages > 1)
  <nav class="pager" aria-label="Pages">
    @if ($page > 1)<a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">Page précédente</a>@endif
    <span>Page {{ $page }} sur {{ $pages }}</span>
    @if ($page < $pages)<a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Page suivante</a>@endif
  </nav>
@endif
@endsection
