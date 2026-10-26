@extends('layouts.web')

@section('title', __('Ils ont été recrutés').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <h1>{{ __('Ils ont été recrutés') }}</h1>
  <p class="meta">{{ __('Des sportifs qui ont décroché un essai, un contrat ou une bourse grâce à leurs vidéos.') }}</p>
  <div class="mgrid stories" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
    @forelse ($rows as $s)
      <article class="card story">
        <div style="display:flex;gap:10px;align-items:center"><x-av :name="$s->name" :sport="$s->sport" />
          <div><b>{{ $s->name }}</b><div class="meta">{{ $s->sport }} · {{ $s->from_city }}</div></div></div>
        <blockquote>« {{ $s->story }} »</blockquote>
        <span class="pill ok" style="align-self:flex-start">{{ __('Recruté par') }} {{ $s->recruited_by }} · {{ $s->year }}</span>
        @if ($s->demo)<span class="meta" style="font-size:12px">{{ __('Exemple de démonstration : histoire inventée.') }}</span>@endif
      </article>
    @empty
      <p class="meta">{{ __('Les premières histoires arrivent bientôt.') }}</p>
    @endforelse
  </div>

  @if ($me && $me['role'] === 'athlete')
    <details class="card" style="margin-top:20px">
      <summary><b>🎉 {{ __('Tu as été recruté grâce à') }} {{ config('terrain.app_name') }} ? {{ __('Raconte-nous !') }}</b></summary>
      <form method="POST" action="{{ route('stories.store') }}" style="display:flex;flex-direction:column;gap:10px;margin-top:10px">@csrf
        <label for="recruited_by">{{ __('Club, académie ou université') }}<input id="recruited_by" name="recruited_by" value="{{ old('recruited_by') }}" required></label>
        <label for="year">{{ __('Année') }}<input id="year" name="year" inputmode="numeric" maxlength="4" value="{{ old('year', now()->year) }}" required></label>
        <label for="story">{{ __('Ton histoire en quelques phrases') }}<textarea id="story" name="story" rows="4" required>{{ old('story') }}</textarea></label>
        <label class="check"><input type="checkbox" name="consent" value="1"> {{ __('J’accepte que mon prénom, mon sport et mon histoire soient publiés (avec l’accord de mon tuteur si j’ai moins de 18 ans).') }}</label>
        @foreach (['recruited_by', 'year', 'story', 'consent'] as $e)@error($e)<p class="field-error" style="margin:0">{{ $message }}</p>@enderror @endforeach
        <button class="btn" type="submit">{{ __('Envoyer') }}</button>
      </form>
    </details>
  @endif
</div>
@endsection
