@extends('layouts.app')

@section('title', 'Installation de l’admin – '.config('terrain.app_name'))
@section('noindex', '1')

@section('content')
<div class="doc gut">
  <div class="narrow" style="max-width:460px">
    <span class="eyebrow">Premier lancement</span>
    <h1>Créer le compte administrateur</h1>
    <p class="meta">Ce compte a tous les droits. Tu pourras ensuite ajouter d’autres admins et modérateurs.</p>
    <form method="POST" action="{{ route('admin.setup') }}" class="card">
      @csrf
      <label for="name">Ton nom<input id="name" name="name" value="{{ old('name') }}" required maxlength="120"></label>
      <label for="email">Identifiant (e-mail)<input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required></label>
      <label for="password">Mot de passe (10 caractères minimum)<input id="password" name="password" type="password" autocomplete="new-password" required minlength="10"></label>
      <label for="password_confirmation">Le même mot de passe<input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></label>
      @if ($needsKey)
        <label for="setup_key">Code d’installation<input id="setup_key" name="setup_key" type="password" required><small>La valeur de <code>TERRAIN_ADMIN_PASSWORD</code> dans le fichier <code>.env</code> : elle empêche quelqu’un d’autre de créer ce compte avant toi.</small></label>
      @endif
      @if ($errors->any())<p class="field-error" style="margin:0">{{ $errors->first() }}</p>@endif
      <button class="btn" type="submit">Créer mon compte et entrer</button>
    </form>
  </div>
</div>
@endsection
