@extends('layouts.web')

@section('title', 'Confirmer le paiement – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:520px">
    <h1>Confirmer le paiement</h1>
    <div class="card">
      <div class="spec">
        <div class="r"><span>Offre</span><b>{{ \App\Terrain\Growth\Payments::label($p->product) }}@if ($p->months) · {{ $p->months }} jours @endif</b></div>
        <div class="r"><span>Montant</span><b>{{ number_format($p->amount, 0, ',', ' ') }} {{ $p->currency }}</b></div>
        <div class="r"><span>Numéro</span><b>{{ $p->phone }}</b></div>
        <div class="r"><span>Référence</span><b>{{ $p->reference }}</b></div>
      </div>
      <form method="POST" action="{{ route('payments.simulation', $p->reference) }}" style="display:flex;flex-direction:column;gap:10px">@csrf
        <label for="code">Code de confirmation (simulation : 4 chiffres au choix)<input id="code" name="code" inputmode="numeric" maxlength="4" autocomplete="one-time-code" required autofocus></label>
        @error('code')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
        <button class="btn" type="submit">Valider</button>
      </form>
      <p class="meta" style="margin:0">Paiement simulé : aucun argent n'est débité. Avec les clés CinetPay, le client valide sur son téléphone avec son code Orange Money ou MTN MoMo.</p>
    </div>
  </div>
</div>
@endsection
