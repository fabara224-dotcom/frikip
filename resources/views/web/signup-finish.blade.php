@extends('layouts.web')

@section('title', __('Dernière étape').' – '.config('terrain.app_name'))

@php
  $via = ['phone' => __('ton numéro'), 'google' => 'Google', 'facebook' => 'Facebook', 'tiktok' => 'TikTok'][$pending['via']] ?? $pending['via'];
@endphp

@section('content')
<div class="page gut">
  <div class="narrow signup" style="max-width:680px">
    <h1>{{ __('Dernière étape') }}</h1>
    <p class="pub-lead">
      @if ($pending['via'] === 'phone'){{ __('Numéro vérifié') }} : <b>{{ $pending['phone'] }}</b> ✓
      @else{{ __('Connecté avec :p', ['p' => $via]) }}@if ($pending['name']) : <b>{{ $pending['name'] }}</b>@endif ✓@endif
      {{ __('Il reste à dire qui tu es.') }}</p>

    <form method="POST" action="{{ route('signup.finish') }}" class="card" id="su" novalidate>@csrf
      <h2>{{ __('Qui es-tu ?') }}</h2>
      @include('web.partials.signup-types')
      @error('type')<p class="field-error" role="alert">{{ $message }}</p>@enderror
      <h2 style="margin-top:18px">{{ __('Ton profil') }}</h2>
      @include('web.partials.signup-fields', ['credentials' => false, 'prefillName' => $pending['name']])
      <button class="btn big" type="submit">{{ __('Créer mon compte') }}</button>
    </form>
  </div>
</div>
@endsection

@push('scripts')
@include('web.partials.signup-js')
@endpush
