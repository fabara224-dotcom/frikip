@extends('layouts.admin')
@section('title', 'Admins – Admin')
@section('content')
@include('admin._nav', ['title' => 'Comptes admin', 'lead' => 'Les personnes qui peuvent entrer dans l’espace admin. Un modérateur gère les membres et les contenus ; un administrateur a tous les droits.'])

@php
  $f = $edit;
@endphp
<div class="adm-grid">
  <section class="panel">
    <h2>{{ $f ? 'Modifier '.$f->name : 'Ajouter un admin' }}</h2>
    <form method="POST" action="{{ route('admin.admins.save') }}" class="adform">@csrf
      @if ($f)<input type="hidden" name="id" value="{{ $f->id }}">@endif
      <div class="formgrid">
        <label class="wide">Nom<input name="name" value="{{ old('name', $f->name ?? '') }}" required maxlength="120"></label>
        <label class="wide">Identifiant (e-mail)<input name="email" type="email" value="{{ old('email', $f->email ?? '') }}" required></label>
        <label>Rôle<select name="role">@foreach ($roles as $k => $l)<option value="{{ $k }}" @selected(old('role', $f->role ?? 'moderateur') === $k)>{{ $l }}</option>@endforeach</select></label>
        <label>{{ $f ? 'Nouveau mot de passe (facultatif)' : 'Mot de passe' }}<input name="password" type="password" autocomplete="new-password" minlength="10" @unless ($f) required @endunless><small>10 caractères minimum</small></label>
      </div>
      <div class="formbar"><button class="btn" type="submit">{{ $f ? 'Enregistrer' : 'Créer le compte' }}</button>@if ($f)<a class="btn ghost" href="{{ route('admin.admins') }}">Annuler</a>@endif</div>
    </form>
  </section>
  <section class="panel">
    <h2>Comptes</h2>
    @foreach ($rows as $a)
      <div class="line {{ $a->active ? '' : 'muted' }}">
        <span><b>{{ $a->name }} @if ($a->id === $admin->id)<span class="pill">Toi</span>@endif</b>
          <small>{{ $a->email }} · {{ $roles[$a->role] ?? $a->role }}</small>
          <small>{{ $a->last_login_at ? 'Dernière connexion '.\Illuminate\Support\Carbon::parse($a->last_login_at)->diffForHumans() : 'Jamais connecté' }}{{ $a->active ? '' : ' · désactivé' }}</small></span>
        <span class="actbtns">
          <a class="btn ghost sm" href="{{ route('admin.admins', ['modifier' => $a->id]) }}">Modifier</a>
          @if ($a->id !== $admin->id)
            <form method="POST" action="{{ route('admin.admins.action', $a->id) }}">@csrf<button class="btn ghost sm" type="submit">{{ $a->active ? 'Désactiver' : 'Réactiver' }}</button></form>
            <form method="POST" action="{{ route('admin.admins.action', $a->id) }}" onsubmit="return confirm('Supprimer ce compte admin ?')">@csrf<input type="hidden" name="action" value="supprimer"><button class="btn ghost sm" type="submit">Supprimer</button></form>
          @endif
        </span>
      </div>
    @endforeach
  </section>
</div>
@endsection
