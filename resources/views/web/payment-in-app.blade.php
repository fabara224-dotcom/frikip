@extends('layouts.web')

@section('title', \App\Terrain\Growth\Payments::label($product).' – '.config('terrain.app_name'))

@section('content')
{{-- Dans l'app mobile, les achats numériques ne sont pas proposés (règles de Google Play et de l'App Store). --}}
<div class="page gut">
  <div class="narrow pub">
    <h1>{{ __(\App\Terrain\Growth\Payments::label($product)) }}</h1>
    <p class="pub-lead">{{ __('Cette offre n’est pas disponible dans l’application.') }}</p>
    <p class="meta">{{ __('Si tu as déjà cette offre, elle est active ici aussi : elle est liée à ton compte.') }}</p>
    <a class="btn ghost" href="{{ route('feed') }}">{{ __('Retour au fil') }}</a>
  </div>
</div>
@endsection
