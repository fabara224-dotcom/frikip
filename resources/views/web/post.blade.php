@extends('layouts.web')

@section('title', \Illuminate\Support\Str::limit($post->body ?: __('Publication de :name', ['name' => $post->author_name]), 60).' – '.config('terrain.app_name'))

@push('meta')
<meta property="og:title" content="{{ $post->author_name }} – {{ config('terrain.app_name') }}">
<meta property="og:description" content="{{ \Illuminate\Support\Str::limit((string) $post->body, 150) }}">
@if ($post->photo)<meta property="og:image" content="{{ asset($post->photo) }}">@endif
@endpush

@section('content')
<div class="page gut actu">
  <a class="backlink" href="{{ route('posts') }}">← {{ __('Actu') }}</a>
  @include('web.partials.post-card', ['post' => $post, 'full' => true])

  <section class="post-thread" id="commentaires">
    <h2>{{ trans_choice(':n commentaire|:n commentaires', count($allComments), ['n' => count($allComments)]) }}</h2>
    @foreach ($allComments as $c)
      <div class="cmt">
        <span class="av">{{ mb_strtoupper(mb_substr($c->author_name, 0, 1)) }}</span>
        <div><b>{{ $c->author_name }}</b><p>{{ $c->body }}</p><small class="meta">{{ \Illuminate\Support\Carbon::parse($c->created_at)->locale(app()->getLocale())->diffForHumans() }}</small></div>
      </div>
    @endforeach
    @if ($me)
      <form class="post-reply" method="POST" action="{{ route('posts.comment', $post->id) }}">@csrf
        <input name="body" maxlength="1000" placeholder="{{ __('Écrire un commentaire…') }}" aria-label="{{ __('Commentaire') }}" required autofocus>
        <button class="btn sm" type="submit">{{ __('Envoyer') }}</button>
      </form>
      @error('body')<p class="field-error">{{ $message }}</p>@enderror
    @else
      <p class="meta"><a href="{{ route('phone.login') }}">{{ __('Connecte-toi') }}</a> {{ __('pour commenter.') }}</p>
    @endif
  </section>
</div>
@include('web.partials.post-scripts')
@endsection
