@extends('layouts.web')

@section('title', 'Connexion – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:440px">
    <h1>Connexion</h1>
    @include('web.partials.social-buttons', ['providers' => \App\Terrain\SocialLogin::providers()])
    <div class="ordiv"><span>{{ __('ou avec un e-mail') }}</span></div>
    @unless ($realAccounts ?? false)<p class="meta">Le même compte que dans l'application mobile.</p>@endunless
    <form method="POST" action="{{ route('login') }}" class="card">@csrf
      <label for="email">E-mail<input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required></label>
      <label for="password">Mot de passe<input id="password" name="password" type="password" autocomplete="current-password" required></label>
      @if ($errors->any())<p class="field-error" style="margin:0">{{ $errors->first() }}</p>@endif
      <button class="btn" type="submit">{{ __('Se connecter') }}</button>
      @unless ($demo)<a class="meta" href="{{ route('password.forgot') }}">{{ __('Mot de passe oublié ?') }}</a>@endunless
    </form>
    <p>{{ __('Pas encore de compte ?') }} <a class="btn ghost sm" href="{{ route('register') }}">{{ __('Créer un compte') }}</a></p>
  </div>
</div>
@endsection
