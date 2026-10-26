@extends('layouts.web')

@section('title', 'Paiement – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:520px;text-align:center">
    @if ($p->status === 'accepted')
      <div class="bigicon ok">✓</div>
      <h1>Paiement réussi</h1>
      <p>{{ ['premium' => 'Premium est activé pour '.$p->months.' jours.', 'structure' => 'Structure Pro est activée pour '.$p->months.' jours : ta page est mise en avant.', 'montage' => 'Ta commande de montage est payée. Notre équipe s’en occupe et te prévient dès qu’elle est prête.', 'boost' => 'Ton profil est boosté pour '.$p->months.' jours.', 'sans_pub' => __('Plus aucune publicité pendant :n jours.', ['n' => $p->months]), 'coaching' => __('Ton coaching est réservé. Un coach te répond très vite dans ton espace coaching.')][$p->product] ?? 'Paiement confirmé.' }} Merci !</p>
      @if ($p->product === 'montage')<a class="btn" href="{{ route('video.orders') }}">{{ __('Suivre ma commande') }}</a>@endif
      @if ($p->product === 'coaching')<a class="btn" href="{{ route('coaching.follow') }}">{{ __('Ouvrir mon suivi') }}</a>@endif
    @elseif (in_array($p->status, ['refused', 'expired'], true))
      <div class="bigicon ko">✕</div>
      <h1>Paiement refusé</h1>
      <p>Aucun montant n'a été débité. Vérifie ton solde Mobile Money et réessaie.</p>
    @else
      <div class="bigicon wait">…</div>
      <h1>Paiement en attente</h1>
      <p>Valide le paiement sur ton téléphone (code Orange Money ou MTN MoMo). Cette page se met à jour toute seule.</p>
      <script>setTimeout(function () { location.reload(); }, 6000);</script>
    @endif
    <p class="meta">Référence : {{ $p->reference }} · {{ number_format($p->amount, 0, ',', ' ') }} {{ $p->currency }}</p>
    <div class="row" style="justify-content:center"><a class="btn" href="{{ route('me') }}">Retour à mon compte</a>
      @if ($p->status !== 'accepted' && $p->status !== 'pending')<a class="btn ghost" href="{{ $p->product === 'coaching' ? route('coaching') : ($p->product === 'montage' ? route('video.orders') : route('premium', $p->product)) }}">Réessayer</a>@endif</div>
  </div>
</div>
@endsection
