@extends('layouts.web')

@section('title', __('Langue et sous-titres').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:640px">
    <p class="meta"><a href="{{ route('feed', ['v' => $v['id']]) }}">← {{ __('Revoir la vidéo') }}</a></p>
    <h1>{{ __('Langue et sous-titres') }}</h1>
    <p><b>{{ \App\Terrain\DataLang::video($v, 'title') }}</b></p>
    @if (session('status'))<p class="note">{{ session('status') }}</p>@endif
    @if (($v['subtitle_status'] ?? null) === 'en_cours')<p class="note">{{ __('Sous-titres automatiques en cours de création… Reviens dans quelques minutes.') }}</p>@endif
    @if (($v['subtitle_status'] ?? null) === 'erreur')<p class="field-error">{{ __('Les sous-titres automatiques n’ont pas pu être créés. Envoie un fichier .srt à la place.') }}</p>@endif
    <form method="POST" action="{{ route('videos.edit', $v['id']) }}" enctype="multipart/form-data" class="card">@csrf
      @include('web.partials.video-lang-fields', ['v' => $v, 'auto' => $auto, 'orig' => $orig])
      <button class="btn" type="submit">{{ __('Enregistrer') }}</button>
    </form>
  </div>
</div>
@endsection
