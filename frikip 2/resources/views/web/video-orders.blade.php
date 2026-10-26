@extends('layouts.web')

@section('title', __('Mes commandes de montage').' – '.config('terrain.app_name'))

@php
  $status = \App\Http\Controllers\VideoServiceController::STATUS;
@endphp

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:720px">
    <h1>{{ __('Mes commandes de montage') }}</h1>
    <div class="list">
      @forelse ($rows as $o)
        <div class="li orderli">
          <span class="grow">
            <span class="t">{{ __($o->package_label) }} · {{ number_format($o->amount, 0, ',', ' ') }} {{ $o->currency }}</span>
            <span class="s">{{ __('réf.') }} {{ $o->reference }} · {{ \Illuminate\Support\Carbon::parse($o->created_at)->translatedFormat('j M Y') }}</span>
            @if ($o->status === 'livree' && $o->delivered_url)
              <a class="btn sm" href="{{ $o->delivered_url }}" target="_blank" rel="noopener">{{ __('Voir mon montage') }}</a>
              <a class="btn ghost sm" href="{{ route('publish') }}">{{ __('Le publier sur mon profil') }}</a>
            @endif
            @if ($o->admin_note)<span class="s">{{ $o->admin_note }}</span>@endif
          </span>
          <span class="pill {{ in_array($o->status, ['livree', 'payee', 'en_cours'], true) ? 'ok' : '' }}">{{ __($status[$o->status] ?? $o->status) }}</span>
          @if ($o->status === 'attente_paiement')
            <form method="POST" action="{{ route('video.order.pay', $o->reference) }}">@csrf<button class="btn sm" type="submit">{{ __('Payer') }}</button></form>
          @endif
        </div>
      @empty
        <p class="meta">{{ __('Aucune commande pour l’instant.') }} <a href="{{ route('video.services') }}">{{ __('Voir les formules de montage') }}</a></p>
      @endforelse
    </div>
  </div>
</div>
@endsection
