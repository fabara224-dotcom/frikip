@extends('layouts.web')

@section('title', $e->title.' – '.config('terrain.app_name'))

@push('meta')
<meta property="og:title" content="{{ $e->title }} – {{ $e->city }}">
<meta property="og:description" content="{{ \Illuminate\Support\Carbon::parse($e->date)->translatedFormat('l j F') }} · {{ $e->place }}">
@endpush

@php
  $d = \Illuminate\Support\Carbon::parse($e->date);
  $full = $count >= $e->capacity;
  $ageOk = ! $athlete || ((! $e->age_min || $athlete['age'] >= $e->age_min) && (! $e->age_max || $athlete['age'] <= $e->age_max));
@endphp

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:680px">
    <a href="{{ route('events') }}">← {{ __('Détections') }}</a>
    <h1>{{ $e->title }}</h1>
    @if ($e->org_name)<p class="meta"><a href="{{ route('clubs.show', $e->org_slug) }}">{{ $e->org_name }}</a> @if ($e->org_verified)<x-ver />@endif</p>@endif
    @if ($e->demo)<p class="note">{{ __('Détection d’exemple (mode démo).') }}</p>@endif
    <div class="card">
      <div class="spec">
        <div class="r"><span>{{ __('Date') }}</span><b>{{ ucfirst($d->translatedFormat('l j F Y')) }}@if ($e->time) · {{ $e->time }}@endif</b></div>
        <div class="r"><span>{{ __('Lieu') }}</span><b>{{ $e->place }}, {{ $e->city }}</b></div>
        <div class="r"><span>{{ __('Sport') }}</span><b>{{ $e->sport }}</b></div>
        @if ($e->age_min || $e->age_max)<div class="r"><span>{{ __('Âge') }}</span><b>{{ $e->age_min }} – {{ $e->age_max }} {{ __('ans') }}</b></div>@endif
        <div class="r"><span>{{ __('Places') }}</span><b>{{ $count }} / {{ $e->capacity }}</b></div>
        <div class="r"><span>{{ __('Prix') }}</span><b>{{ __('Gratuit') }}</b></div>
      </div>
      @if ($e->description)<p style="margin:0">{{ $e->description }}</p>@endif

      @if ($reg)
        <a class="btn" href="{{ route('tickets.show', $reg->code) }}">🎟️ {{ __('Voir mon billet') }}</a>
      @elseif ($isOwner)
        <a class="btn" href="{{ route('events.manage', $e->id) }}">{{ __('Liste des inscrits et pointage') }}</a>
      @elseif (! $me)
        <a class="btn" href="{{ route('phone.login') }}">{{ __('Créer un compte pour s’inscrire') }}</a>
      @elseif ($me['role'] !== 'athlete')
        <p class="meta" style="margin:0">{{ __('L’inscription est réservée aux sportifs.') }}</p>
      @elseif ($full)
        <p class="meta" style="margin:0">{{ __('Complet.') }}</p>
      @elseif (! $ageOk)
        <p class="meta" style="margin:0">{{ __('Cette détection n’est pas ouverte à ton âge.') }}</p>
      @else
        <form method="POST" action="{{ route('events.register', $e->id) }}">@csrf<button class="btn" type="submit">{{ __('Je m’inscris') }}</button></form>
      @endif
    </div>
    <a class="btn wa" style="margin-top:12px" href="https://wa.me/?text={{ rawurlencode($e->title.' – '.$d->translatedFormat('j F').' à '.$e->city.' : '.route('events.show', $e->id)) }}" target="_blank" rel="noopener">{{ __('Partager sur WhatsApp') }}</a>
    <p class="note" style="margin-top:14px">{{ __('Une détection est toujours gratuite. Si quelqu’un te demande de l’argent pour y participer, signale-le.') }}</p>
  </div>
  <x-fabara for="detection" />
</div>
@endsection
