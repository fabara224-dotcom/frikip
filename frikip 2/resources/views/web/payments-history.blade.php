@extends('layouts.web')

@section('title', __('Mes paiements').' – '.config('terrain.app_name'))

@php
  $labels = ['accepted' => [__('Payé'), 'ok'], 'pending' => [__('En attente'), ''], 'refused' => [__('Refusé'), 'warn'], 'expired' => [__('Abandonné'), '']];
  $methods = ['om' => 'Orange Money', 'momo' => 'MTN MoMo', 'card' => __('Carte bancaire'), 'OMGN' => 'Orange Money', 'MTNGN' => 'MTN MoMo'];
@endphp

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:720px">
    <h1>{{ __('Mes paiements') }}</h1>
    @if ($member && $member->premium_until && \Illuminate\Support\Carbon::parse($member->premium_until)->isFuture())
      <p class="note">✅ {{ __('Premium actif jusqu’au :d.', ['d' => \Illuminate\Support\Carbon::parse($member->premium_until)->translatedFormat('j F Y')]) }}</p>
    @endif
    @if ($member && $member->boost_until && \Illuminate\Support\Carbon::parse($member->boost_until)->isFuture())
      <p class="note">🚀 {{ __('Boost actif jusqu’au :d.', ['d' => \Illuminate\Support\Carbon::parse($member->boost_until)->translatedFormat('j F Y')]) }}</p>
    @endif
    <div class="list">
      @forelse ($rows as $p)
        @php([$st, $cls] = $labels[$p->status] ?? [$p->status, ''])
        <a class="li" href="{{ route('payments.return', $p->reference) }}">
          <span class="grow"><span class="t">{{ \App\Terrain\Growth\Payments::label($p->product) }}@if ($p->months) · {{ $p->months }} {{ __('jours') }}@endif · <b>{{ number_format($p->amount, 0, ',', ' ') }} {{ $p->currency }}</b></span>
            <span class="s">{{ \Illuminate\Support\Carbon::parse($p->created_at)->translatedFormat('j M Y, H:i') }} · {{ $methods[$p->method] ?? $p->method }} · {{ __('réf.') }} {{ $p->reference }}@if ($p->provider === 'simulation') · {{ __('simulation') }}@endif</span></span>
          <span class="pill {{ $cls }}">{{ $st }}</span>
        </a>
      @empty
        <p class="meta">{{ __('Aucun paiement pour l’instant.') }}</p>
      @endforelse
    </div>
    <p class="meta" style="margin-top:14px">{{ __('Un souci avec un paiement ? Écris-nous en donnant la référence.') }} <a href="{{ route('support') }}">{{ __('Aide et contact') }}</a></p>
  </div>
</div>
@endsection
