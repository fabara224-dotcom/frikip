@extends('layouts.web')

@section('title', __('Nouveau mot de passe').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:440px">
    <h1>{{ __('Nouveau mot de passe') }}</h1>
    <form method="POST" action="{{ route('password.reset', $token) }}" class="card">@csrf
      <label for="password">{{ __('Nouveau mot de passe (8 caractères minimum)') }}<input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required></label>
      <label for="password_confirmation">{{ __('Confirme le nouveau mot de passe') }}<input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></label>
      @error('password')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
      <button class="btn" type="submit">{{ __('Enregistrer') }}</button>
    </form>
  </div>
</div>
@endsection
