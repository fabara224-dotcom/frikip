@extends('layouts.admin')
@section('title', 'Journal – Admin')
@section('content')
@include('admin._nav', ['title' => 'Journal', 'lead' => $total.' action(s) enregistrée(s). Ce journal ne peut pas être modifié.'])
@php
  $names = [
    'admin.member.action' => 'Action sur un membre', 'admin.moderation.decide' => 'Décision de modération', 'admin.algorithm.save' => 'Réglage de l’algorithme',
    'admin.reports.handle' => 'Signalement traité', 'admin.moderate' => 'Décision manuelle', 'admin.lift' => 'Décision levée', 'admin.verifications.decide' => 'Vérification',
    'admin.stories.decide' => 'Témoignage', 'admin.partners.decide' => 'Partenariat', 'admin.montages.update' => 'Montage', 'admin.clubs.verify' => 'Club vérifié',
    'admin.challenges.create' => 'Défi créé', 'admin.admins.save' => 'Compte admin', 'admin.admins.action' => 'Compte admin', 'admin.member.manage' => 'Gestion d’un membre',
    'admin.videos.delete' => 'Vidéo supprimée', 'admin.content.delete' => 'Contenu supprimé', 'admin.payments.action' => 'Paiement', 'admin.ads.save' => 'Publicité', 'admin.live.save' => 'Direct', 'admin.challenges.close' => 'Défi clôturé', 'admin.update' => 'Demande de suppression',
  ];
@endphp
<div class="tbl admin-tbl"><table>
  <thead><tr><th>Date</th><th>Par</th><th>Action</th><th>Cible</th><th>Détails</th></tr></thead>
  <tbody>
  @forelse ($rows as $r)
    @php
      $d = json_decode((string) $r->details, true) ?: [];
    @endphp
    <tr>
      <td style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}</td>
      <td>{{ $r->admin_name ?? '–' }}</td>
      <td><b>{{ $names[$r->action] ?? $r->action }}</b></td>
      <td>@if ($r->action === 'admin.member.action' && $r->target)<a href="{{ route('admin.member', $r->target) }}">{{ $r->target }}</a>@else{{ $r->target ?: '–' }}@endif</td>
      <td class="logd">
        @foreach ($d as $k => $v)
          @if ($k === 'changements' && is_array($v))
            @foreach ($v as $key => $chg)<span>{{ \App\Terrain\Growth\Algorithm::SETTINGS[$key][3] ?? $key }} : {{ is_array($chg[0] ?? null) ? '' : \Illuminate\Support\Str::limit(var_export($chg[0], true), 40) }} → {{ \Illuminate\Support\Str::limit(var_export($chg[1], true), 40) }}</span>@endforeach
          @elseif (! is_array($v) && $v !== null && $v !== '' && ! in_array($k, ['group'], true))
            <span>{{ $k }} : {{ \Illuminate\Support\Str::limit((string) $v, 80) }}</span>
          @endif
        @endforeach
      </td>
    </tr>
  @empty
    <tr><td colspan="5" class="meta">Aucune action pour l’instant.</td></tr>
  @endforelse
  </tbody></table></div>
@if ($pages > 1)
  <nav class="pager" aria-label="Pages">
    @if ($page > 1)<a href="{{ route('admin.journal', ['page' => $page - 1]) }}">Plus récentes</a>@endif
    <span>Page {{ $page }} sur {{ $pages }}</span>
    @if ($page < $pages)<a href="{{ route('admin.journal', ['page' => $page + 1]) }}">Plus anciennes</a>@endif
  </nav>
@endif
@endsection
