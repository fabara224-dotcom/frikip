@extends('layouts.admin')
@section('title', 'Mon compte – Admin')
@section('content')
@include('admin._nav', ['title' => 'Mon compte', 'lead' => $a->email.' · '.(\App\Terrain\Growth\AdminAuth::ROLES[$a->role] ?? $a->role)])
<section class="panel" style="max-width:560px">
  <form method="POST" action="{{ route('admin.account.save') }}" class="adform">@csrf
    <div class="formgrid">
      <label class="wide">Nom<input name="name" value="{{ old('name', $a->name) }}" required maxlength="120"></label>
      <label class="wide">Mot de passe actuel<input name="current_password" type="password" autocomplete="current-password" required></label>
      <label>Nouveau mot de passe<input name="password" type="password" autocomplete="new-password" minlength="10"><small>Laisse vide pour le garder</small></label>
      <label>Le même<input name="password_confirmation" type="password" autocomplete="new-password"></label>
    </div>
    <div class="formbar"><button class="btn" type="submit">Enregistrer</button></div>
  </form>
</section>
@endsection
