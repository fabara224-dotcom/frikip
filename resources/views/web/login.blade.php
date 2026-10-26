@extends('layouts.web')

@section('title', 'Connexion – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:440px">
    <h1>Connexion</h1>
    <a class="btn phonebtn" href="{{ route('phone.login') }}"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/></svg> {{ __('Continuer avec mon numéro de téléphone') }}</a>
    <p class="meta" style="text-align:center;margin:6px 0 12px">{{ __('ou avec un e-mail') }}</p>
    @unless ($realAccounts ?? false)<p class="meta">Le même compte que dans l'application mobile.</p>@endunless
    <form method="POST" action="{{ route('login') }}" class="card">@csrf
      <label for="email">E-mail<input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required></label>
      <label for="password">Mot de passe<input id="password" name="password" type="password" autocomplete="current-password" required></label>
      @if ($errors->any())<p class="field-error" style="margin:0">{{ $errors->first() }}</p>@endif
      <button class="btn" type="submit">{{ __('Se connecter') }}</button>
      @unless ($demo)<a class="meta" href="{{ route('password.forgot') }}">{{ __('Mot de passe oublié ?') }}</a>@endunless
    </form>
    <p>Pas encore de compte ? <a href="{{ route('register') }}">Créer un compte</a></p>
  </div>
</div>
@endsection
