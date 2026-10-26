@extends('layouts.admin')
@section('title', 'Témoignages – '.config('terrain.app_name'))
@section('noindex', '1')
@section('content')
<div class="doc gut"><div class="wrap">
  @include('admin._nav', ['title' => '« Ils ont été recrutés »'])
  <p class="meta">Publie seulement les histoires vraies, avec l'accord de la personne (et du tuteur pour un mineur).</p>
  <div class="tbl admin-tbl"><table>
    <thead><tr><th>Sportif</th><th>Recruté par</th><th>Histoire</th><th>Accord</th><th>Statut</th></tr></thead>
    <tbody>
    @forelse ($rows as $s)
      <tr><td><b>{{ $s->name }}</b><br><span class="meta">{{ $s->sport }} · {{ $s->from_city }}</span>@if ($s->demo)<br><span class="badge">exemple</span>@endif</td>
        <td>{{ $s->recruited_by }} ({{ $s->year }})</td><td style="max-width:360px">{{ $s->story }}</td><td>{{ $s->consent ? '✓' : '✕' }}</td>
        <td><form method="POST" action="{{ route('admin.stories.decide', $s->id) }}" class="row" style="gap:6px">@csrf
          <select name="status" aria-label="Statut">@foreach (['en_attente' => 'En attente', 'publie' => 'Publié', 'refuse' => 'Refusé'] as $k => $l)<option value="{{ $k }}" @selected($s->status === $k)>{{ $l }}</option>@endforeach</select>
          <button class="btn ghost sm">OK</button></form></td></tr>
    @empty
      <tr><td colspan="5" class="meta">Aucun témoignage.</td></tr>
    @endforelse
    </tbody></table></div>
</div></div>
@endsection
