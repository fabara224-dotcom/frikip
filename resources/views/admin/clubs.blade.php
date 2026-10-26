@extends('layouts.admin')
@section('title', 'Clubs – '.config('terrain.app_name'))
@section('noindex', '1')
@section('content')
<div class="doc gut"><div class="wrap">
  @include('admin._nav', ['title' => 'Pages clubs, académies et agents'])
  <p class="meta">Vérifie la licence (fédération, FIFA pour les agents) avant de donner le badge.</p>
  <div class="tbl admin-tbl"><table>
    <thead><tr><th>Nom</th><th>Type</th><th>Ville</th><th>Licence</th><th>Badge</th></tr></thead>
    <tbody>
    @foreach ($rows as $o)
      <tr><td><a href="{{ route('clubs.show', $o->slug) }}" target="_blank">{{ $o->name }}</a>@if ($o->demo) <span class="badge">exemple</span>@endif</td><td>{{ $o->type }}</td><td>{{ $o->city }}</td><td>{{ $o->licence ?: '–' }}</td>
        <td><form method="POST" action="{{ route('admin.clubs.verify', $o->id) }}">@csrf<button class="btn {{ $o->verified ? 'ghost' : '' }} sm">{{ $o->verified ? 'Retirer ✓' : 'Vérifier' }}</button></form>
          <form method="POST" action="{{ route('admin.content.delete', ['club', $o->id]) }}" onsubmit="return confirm('Supprimer la page de ce club et ses détections ?')">@csrf<button class="btn ghost sm" type="submit">Supprimer</button></form></td></tr>
    @endforeach
    </tbody></table></div>
</div></div>
@endsection
