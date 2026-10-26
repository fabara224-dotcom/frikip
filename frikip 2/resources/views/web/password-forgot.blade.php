@extends('layouts.web')

@section('title', __('Mot de passe oublié').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:440px">
    <h1>{{ __('Mot de passe oublié') }}</h1>
    @if (session('status'))<p class="note">{{ session('status') }}</p>@endif
    @if (session('dev_link'))
      <p class="note"><b>{{ __('Mode développement') }}</b> : {{ __('aucun serveur d’e-mail n’est configuré, voici le lien envoyé :') }}<br><a href="{{ session('dev_link') }}">{{ session('dev_link') }}</a></p>
    @endif
    <form method="POST" action="{{ route('password.forgot') }}" class="card">@csrf
      <label for="email">{{ __('E-mail du compte') }}<input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required></label>
      @error('email')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
      <button class="btn" type="submit">{{ __('Recevoir un lien') }}</button>
    </form>
    <p class="meta">{{ __('Compte créé avec ton numéro de téléphone ?') }} <a href="{{ route('phone.login') }}">{{ __('Connecte-toi avec un code SMS') }}</a></p>
  </div>
</div>
@endsection
