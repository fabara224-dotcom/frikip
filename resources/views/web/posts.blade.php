@extends('layouts.web')

@section('title', __('Actu').' – '.config('terrain.app_name'))

@section('rail')
  <a class="railhero" href="{{ route('feed') }}">
    <span class="railhero-k">{{ __('Vidéos') }}</span>
    <b>{{ __('Les meilleures actions en vidéo') }}</b>
    <small>{{ __('Dribbles · Buts · Arrêts · Défis') }}</small>
    <span class="btn sm">{{ __('Voir les vidéos') }}</span>
  </a>
  <section class="railbox">
    <header><h2>{{ __('À ne pas manquer') }}</h2><a href="{{ route('explore') }}">{{ __('Voir tout') }} →</a></header>
    <a class="railrow" href="{{ route('matches') }}"><span class="lico"><x-ico name="score" /></span><span class="grow"><b>{{ __('Matchs du jour') }}</b><small>{{ __('Scores en direct et résultats') }}</small></span></a>
    <a class="railrow" href="{{ route('challenges') }}"><span class="lico"><x-ico name="trophy" /></span><span class="grow"><b>{{ __('Défi de la semaine') }}</b><small>{{ __('Vote pour la meilleure action') }}</small></span></a>
    <a class="railrow" href="{{ route('rankings') }}"><span class="lico"><x-ico name="star" /></span><span class="grow"><b>{{ __('Joueurs à suivre') }}</b><small>{{ __('Les plus suivis du moment') }}</small></span></a>
    <a class="railrow" href="{{ route('clubs') }}"><span class="lico"><x-ico name="stadium" /></span><span class="grow"><b>{{ __('Clubs et académies') }}</b><small>{{ __('Trouve une structure près de chez toi') }}</small></span></a>
  </section>
@endsection

@section('content')
<div class="page gut actu">
  <nav class="vswitch" aria-label="{{ __('Fil') }}">
    <a href="{{ route('feed') }}">{{ __('Vidéos') }}</a>
    <a href="{{ route('posts') }}" aria-current="page">{{ __('Actu') }}</a>
  </nav>

  @if ($me)
    <form class="composer" method="POST" action="{{ route('posts.store') }}" enctype="multipart/form-data">@csrf
      <div class="composer-row">
        <span class="av">{{ collect(preg_split('/\s+/', trim($me['name'] ?? '?')))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}</span>
        <textarea name="body" maxlength="2000" rows="2" placeholder="{{ __('Quoi de neuf sur le terrain ?') }}" aria-label="{{ __('Ta publication') }}">{{ old('body') }}</textarea>
      </div>
      <div class="composer-preview" id="post-preview" hidden><img alt=""><button type="button" class="x" id="post-photo-rm" aria-label="{{ __('Retirer la photo') }}">×</button></div>
      @error('body')<p class="field-error">{{ $message }}</p>@enderror
      @error('photo')<p class="field-error">{{ $message }}</p>@enderror
      <div class="composer-acts">
        <label class="composer-btn"><input type="file" name="photo" id="post-photo" accept="image/jpeg,image/png,image/webp" class="visually-hidden"><x-ico name="image" />{{ __('Photo') }}</label>
        <a class="composer-btn" href="{{ ($me['role'] ?? null) === 'athlete' ? route('publish') : route('offers') }}"><x-ico :name="($me['role'] ?? null) === 'athlete' ? 'film' : 'tag'" />{{ ($me['role'] ?? null) === 'athlete' ? __('Vidéo') : __('Offre') }}</a>
        <button class="btn sm" type="submit">{{ __('Publier') }}</button>
      </div>
    </form>
  @else
    <a class="composer composer-guest" href="{{ route('phone.login') }}">
      <span>{{ __('Quoi de neuf sur le terrain ?') }}</span><span class="btn sm">{{ __('Rejoindre Frikip') }}</span>
    </a>
  @endif

  <nav class="chips feedtabs" aria-label="{{ __('Filtrer') }}">
    <a class="chip" href="{{ route('posts') }}" @if ($tab === 'tout') aria-current="true" @endif>{{ __('Pour toi') }}</a>
    <a class="chip" href="{{ route('posts', ['onglet' => 'pays']) }}" @if ($tab === 'pays') aria-current="true" @endif>@td($country)</a>
  </nav>

  @forelse ($posts as $post)
    @include('web.partials.post-card', ['post' => $post])
  @empty
    <div class="post post-empty">
      <b>{{ __('Pas encore de publication.') }}</b>
      <p class="meta">{{ __('Partage ton entraînement, ton match du week-end ou ton prochain objectif : les clubs et les fans te suivront.') }}</p>
    </div>
  @endforelse

  @if ($posts->hasPages())
    <div class="pager">
      @if ($posts->previousPageUrl())<a class="btn ghost sm" href="{{ $posts->previousPageUrl() }}">← {{ __('Plus récentes') }}</a>@endif
      @if ($posts->nextPageUrl())<a class="btn ghost sm" href="{{ $posts->nextPageUrl() }}">{{ __('Plus anciennes') }} →</a>@endif
    </div>
  @endif
</div>
@include('web.partials.post-scripts')
@endsection
