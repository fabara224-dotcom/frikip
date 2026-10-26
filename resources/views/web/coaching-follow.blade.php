@extends('layouts.web')

@section('title', __('Mon coaching').' – '.config('terrain.app_name'))

@php
  $status = \App\Terrain\Growth\Coaching::STATUS;
@endphp

@section('content')
<div class="page gut">
  <div class="narrow">
    <h1>{{ $coach ? __('Espace coach') : __('Mon coaching') }}</h1>

    @if ($coach)
      <p class="pub-lead">{{ __('Les sportifs que tu suis. Réponds à leurs demandes et envoie-leur programmes et bilans.') }}</p>
      @forelse ($coached as $r)
        <a class="listrow" href="{{ route('coaching.thread', $r->reference) }}">
          <span><b>{{ $names[$r->user_key] ?? __('Sportif') }}</b><small>{{ __($r->package_label) }} · {{ __($r->goal) }}@if ($r->ends_at) · {{ __('jusqu’au :d', ['d' => \Illuminate\Support\Carbon::parse($r->ends_at)->format('d/m/Y')]) }}@endif</small></span>
          <span class="pill">{{ __($status[$r->status] ?? $r->status) }}</span>
        </a>
      @empty
        <p class="meta">{{ __('Aucun sportif ne t’est encore confié.') }}</p>
      @endforelse
      <h2 style="margin-top:28px">{{ __('Mes propres réservations') }}</h2>
    @endif

    @forelse ($rows as $r)
      <div class="listrow">
        <span><b>{{ __($r->package_label) }}</b><small>{{ __('Réf.') }} {{ $r->reference }} · {{ __($r->goal) }}@if ($r->coach_id && isset($coaches[$r->coach_id])) · {{ __('Coach :n', ['n' => $coaches[$r->coach_id]]) }}@endif</small></span>
        <span class="pill">{{ __($status[$r->status] ?? $r->status) }}</span>
        @if ($r->status === 'attente_paiement')
          <form method="POST" action="{{ route('coaching.pay', $r->reference) }}">@csrf<button class="btn sm" type="submit">{{ __('Payer') }}</button></form>
        @else
          <a class="btn sm ghost" href="{{ route('coaching.thread', $r->reference) }}">{{ __('Ouvrir') }}</a>
        @endif
      </div>
    @empty
      @unless ($coach)
        <div class="empty-state">
          <p>{{ __('Tu n’as pas encore de coach.') }}</p>
          <a class="btn" href="{{ route('coaching') }}">{{ __('Voir le coaching') }}</a>
        </div>
      @endunless
    @endforelse
  </div>
</div>
@endsection
