<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('terrain.app_name') }}</title>
<meta name="description" content="{{ config('terrain.app_name') }} : le TikTok du football. Vidéos courtes, matchs en direct, joueurs, fans et clubs.">
<meta name="theme-color" content="#FFFFFF">
<link rel="icon" href="{{ asset('brand/logo.svg') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
@php($v = @filemtime(public_path('frikip-app/css/app.css')) ?: 1)
<link rel="stylesheet" href="{{ asset('frikip-app/css/app.css') }}?v={{ $v }}">
<style>:root{padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)}body{margin:0}img{max-width:100%}[hidden]{display:none!important}</style>
</head>
<body>
<div class="app" id="app"></div>
<script>window.FRIKIP_CONFIG = @json($config);</script>
@foreach (['config', 'services/state', 'services/api', 'ui/components', 'ui/scene', 'views/feed', 'views/home', 'views/explore', 'views/matches', 'views/messages', 'views/profile', 'views/notifications', 'views/publish', 'views/menu', 'app'] as $f)
<script src="{{ asset('frikip-app/js/'.$f.'.js') }}?v={{ $v }}"></script>
@endforeach
</body>
</html>
