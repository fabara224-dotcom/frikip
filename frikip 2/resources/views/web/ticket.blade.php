@extends('layouts.web')

@section('title', __('Billet').' – '.$e->title)

@php($d = \Illuminate\Support\Carbon::parse($e->date))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:420px">
    <div class="ticket">
      <div class="thead2"><span>🎟️ {{ __('Billet de détection') }}</span><b>{{ config('terrain.app_name') }}</b></div>
      <h2>{{ $e->title }}</h2>
      <p>{{ ucfirst($d->translatedFormat('l j F Y')) }}@if ($e->time) · {{ $e->time }}@endif<br>{{ $e->place }}, {{ $e->city }}</p>
      <div id="qr" class="tqr" aria-label="QR code du billet"></div>
      <div class="tcode">{{ $r->code }}</div>
      <p class="tname">{{ $r->name }}</p>
      @if ($r->checked_in_at)
        <span class="pill ok">✓ {{ __('Présence validée à') }} {{ \Illuminate\Support\Carbon::parse($r->checked_in_at)->format('H:i') }}</span>
      @endif
      @if ($isOwner && ! $r->checked_in_at)
        <form method="POST" action="{{ route('tickets.checkin', $r->code) }}">@csrf<button class="btn" type="submit">{{ __('Valider la présence') }}</button></form>
      @endif
    </div>
    <p class="meta" style="text-align:center">{{ __('Fais une capture d’écran : le billet s’affiche même sans internet. Apporte aussi ta pièce d’identité.') }}</p>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>try { new QRCode(document.getElementById('qr'), { text: @json(route('tickets.show', $r->code)), width: 200, height: 200 }); } catch (e) {}</script>
@endpush
