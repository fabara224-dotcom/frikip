@extends('layouts.web')

@section('title', __('Vidéos enregistrées').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <h1>{{ __('Vidéos enregistrées') }}</h1>
  <p class="pub-lead">{{ __('Les vidéos que tu as gardées avec le bouton ••• > Enregistrer.') }}</p>
  @if (count($items))
    <div class="savedgrid">
      @foreach ($items as $it)
        @php $v = $it['video']; $a = $it['athlete']; @endphp
        <a class="savedcard" href="{{ route('feed', ['v' => $v['id']]) }}">
          <span class="th">@if (! empty($v['poster']))<img src="{{ $v['poster'] }}" alt="" loading="lazy">@else<video src="{{ $v['url'] }}#t=0.5" muted preload="metadata" playsinline></video>@endif</span>
          <b>{{ $v['title'] }}</b><small>{{ $a['name'] }}</small>
        </a>
      @endforeach
    </div>
  @else
    <div class="empty-state">
      <p>{{ __('Aucune vidéo enregistrée pour l’instant.') }}</p>
      <a class="btn" href="{{ route('feed') }}">{{ __('Retour au fil') }}</a>
    </div>
  @endif
</div>
@endsection
