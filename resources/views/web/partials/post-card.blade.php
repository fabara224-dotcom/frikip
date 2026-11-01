{{-- Une publication de l'Actu : auteur, texte, photo, j'aime, commentaires. --}}
@php
  $initials = collect(preg_split('/\s+/', trim($post->author_name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
  $isLiked = isset($liked[$post->id]);
  $mine = $me && $me['id'] === $post->user_key;
  $when = \Illuminate\Support\Carbon::parse($post->created_at)->locale(app()->getLocale())->diffForHumans();
  $profile = $post->athlete_id ? route('athletes.show', $post->athlete_id) : null;
  $shown = $comments[$post->id] ?? [];
  $full = $full ?? false;
@endphp
<article class="post" id="post-{{ $post->id }}">
  <header class="post-hd">
    @if ($profile)<a href="{{ $profile }}" class="av">{{ $initials }}</a>@else<span class="av">{{ $initials }}</span>@endif
    <div class="post-who">
      @if ($profile)<a href="{{ $profile }}"><b>{{ $post->author_name }}</b></a>@else<b>{{ $post->author_name }}</b>@endif
      <small>{{ $post->author_role === 'recruiter' ? __('Recruteur') : __('Joueur') }}@if ($post->country) · @td($post->country)@endif · <a href="{{ route('posts.show', $post->id) }}">{{ $when }}</a></small>
    </div>
    @if ($me)
      <details class="post-more">
        <summary aria-label="{{ __('Options') }}">⋯</summary>
        <div class="card">
          @if ($mine)
            <form method="POST" action="{{ route('posts.destroy', $post->id) }}" onsubmit="return confirm(@js(__('Supprimer cette publication ?')))">@csrf<button type="submit" class="danger">{{ __('Supprimer') }}</button></form>
          @else
            <form method="POST" action="{{ route('posts.report', $post->id) }}">@csrf<button type="submit">{{ __('Signaler') }}</button></form>
          @endif
        </div>
      </details>
    @endif
  </header>
  @if ($post->body)<p class="post-body">{{ $post->body }}</p>@endif
  @if ($post->photo)
    <a class="post-photo" href="{{ asset($post->photo) }}" target="_blank" rel="noopener"><img src="{{ asset($post->photo) }}" alt="{{ __('Photo de :name', ['name' => $post->author_name]) }}" loading="lazy"></a>
  @endif
  <div class="post-stats">
    <span><i class="heart">❤</i> <b data-likes>{{ $post->likes }}</b></span>
    <a href="{{ route('posts.show', $post->id) }}#commentaires">{{ trans_choice(':n commentaire|:n commentaires', $post->comments, ['n' => $post->comments]) }}</a>
  </div>
  <div class="post-acts">
    <button type="button" class="post-like @if ($isLiked) on @endif" data-like="{{ route('posts.like', $post->id) }}" aria-pressed="{{ $isLiked ? 'true' : 'false' }}">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.2-9.4C1.6 7.6 3.9 4.5 7.2 4.5c2 0 3.6 1.1 4.8 2.8 1.2-1.7 2.8-2.8 4.8-2.8 3.3 0 5.6 3.1 4.4 6.6-1.7 4.8-9.2 9.4-9.2 9.4z"/></svg>{{ __('J’aime') }}
    </button>
    <a href="{{ route('posts.show', $post->id) }}#commentaires"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5h16v10.5H9l-5 4z"/></svg>{{ __('Commenter') }}</a>
    <button type="button" data-share="{{ route('posts.show', $post->id) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 5l6 6-6 6M20 11H10a6 6 0 0 0-6 6v1"/></svg>{{ __('Partager') }}</button>
  </div>
  @if (! $full && count($shown))
    <div class="post-cmts">
      @if ($post->comments > count($shown))<a class="meta" href="{{ route('posts.show', $post->id) }}#commentaires">{{ __('Voir les :n commentaires', ['n' => $post->comments]) }}</a>@endif
      @foreach ($shown as $c)
        <p><b>{{ $c->author_name }}</b> {{ $c->body }}</p>
      @endforeach
    </div>
  @endif
  @if ($me && ! $full)
    <form class="post-reply" method="POST" action="{{ route('posts.comment', $post->id) }}">@csrf
      <input name="body" maxlength="1000" placeholder="{{ __('Écrire un commentaire…') }}" aria-label="{{ __('Commentaire') }}" required>
      <button class="btn sm" type="submit">{{ __('Envoyer') }}</button>
    </form>
  @endif
</article>
