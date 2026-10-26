@extends('layouts.admin')
@section('title', 'Paiements – '.config('terrain.app_name'))
@section('noindex', '1')
@section('content')
<div class="doc gut"><div class="wrap">
  @include('admin._nav', ['title' => 'Paiements'])
  <p><b>Total encaissé : {{ number_format($total, 0, ',', ' ') }} {{ config('terrain.currency') }}</b> · Mode : {{ \App\Terrain\Growth\Payments::live() ? 'CinetPay (réel)' : 'simulation (clés CinetPay non configurées)' }}</p>
  <div class="tbl admin-tbl"><table>
    <thead><tr><th>Date</th><th>Référence</th><th>Compte</th><th>Offre</th><th>Montant</th><th>Moyen</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    @forelse ($rows as $p)
      <tr><td>{{ \Illuminate\Support\Carbon::parse($p->created_at)->format('d/m H:i') }}</td><td>{{ $p->reference }}</td><td class="meta"><a href="{{ route('admin.member', $p->user_key) }}">{{ \Illuminate\Support\Str::limit($p->user_key, 14) }}</a></td>
        <td>{{ $p->product }} · {{ $p->months }} j</td><td>{{ number_format($p->amount, 0, ',', ' ') }} {{ $p->currency }}</td>
        <td>{{ ['om' => 'Orange Money', 'momo' => 'MTN MoMo', 'card' => 'Carte'][$p->method] ?? $p->method }} · {{ $p->provider }}</td>
        <td><span class="badge {{ $p->status }}">{{ ['accepted' => 'Payé', 'pending' => 'En attente', 'refused' => 'Refusé'][$p->status] ?? $p->status }}</span></td>
        <td>@if ($p->status !== 'accepted')<div class="actbtns">
          <form method="POST" action="{{ route('admin.payments.action', $p->id) }}" onsubmit="return confirm('Confirmer que ce paiement a bien été reçu (espèces, virement…) ? L’offre sera activée.')">@csrf<input type="hidden" name="action" value="valider"><button class="btn ghost sm" type="submit">Valider</button></form>
          @if ($p->status === 'pending')<form method="POST" action="{{ route('admin.payments.action', $p->id) }}">@csrf<input type="hidden" name="action" value="annuler"><button class="btn ghost sm" type="submit">Annuler</button></form>@endif
        </div>@endif</td></tr>
    @empty
      <tr><td colspan="7" class="meta">Aucun paiement.</td></tr>
    @endforelse
    </tbody></table></div>
</div></div>
@endsection
