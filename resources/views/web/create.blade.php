@extends('layouts.web')

@section('title', __('Publier').' – '.config('terrain.app_name'))

@php
  $role = $me['role'] ?? null;
  $items = [];
  $items[] = [route('live.go'), 'live', __('Lancer un live'), __('Filme et commente un match en direct depuis ton téléphone.')];
  $items[] = [route('matches'), 'score', __('Commenter un match'), __('Ouvre le salon d’un match et réagis en direct avec les autres supporters.')];
  if ($role === 'recruiter') {
      $items[] = [route('events'), 'calendar', __('Publier une détection'), __('Annonce une journée de détection ou un essai.')];
      $items[] = [route('offers'), 'tag', __('Publier une offre'), __('Cherche un joueur pour un poste précis.')];
      $items[] = [route('live.create'), 'tv', __('Diffuser un match de mon club'), __('Avec une caméra ou un logiciel de diffusion (OBS).')];
  }
  if ($role === 'fan') {
      $items[] = [route('register', ['type' => 'proche']), 'ball', __('Mettre en avant un proche'), __('Crée le profil de joueur de ton frère, ton fils, un ami… et publie ses vidéos.')];
  }
@endphp

@section('content')
<div class="page gut">
  <div class="narrow">
    <h1>{{ __('Publier') }}</h1>
    <p class="pub-lead">{{ __('Que veux-tu partager ?') }}</p>
    <div class="extiles one">
      @foreach ($items as [$href, $ico, $title, $sub])
        <a class="extile" href="{{ $href }}"><x-ico :name="$ico" /><span><b>{{ $title }}</b><small>{{ $sub }}</small></span></a>
      @endforeach
    </div>
  </div>
</div>
@endsection
