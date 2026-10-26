@extends('layouts.app')

@section('title', 'Admin – '.config('terrain.app_name'))
@section('noindex', '1')

@section('content')
<div class="doc gut">
  <div class="narrow" style="max-width:420px">
    <span class="eyebrow">Espace admin</span>
    <h1>Connexion</h1>
    <form method="POST" action="{{ route('admin.login') }}" class="card">
      @csrf
      <label for="email">Identifiant (e-mail)
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
      </label>
      <label for="password">Mot de passe
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </label>
      @if ($errors->any())<p class="field-error" style="margin:0">{{ $errors->first() }}</p>@endif
      <button class="btn" type="submit">Se connecter</button>
    </form>
    <p class="meta">Mot de passe oublié ? Un autre administrateur peut le changer dans Admin &gt; Admins, ou sur le serveur : <code>php artisan frikip:admin frikip@gmail.com</code></p>
  </div>
</div>
@endsection
