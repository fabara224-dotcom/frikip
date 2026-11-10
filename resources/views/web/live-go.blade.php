@extends('layouts.web')

@section('title', __('Lancer un live').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow pub">
    <h1>{{ __('Lancer un live') }}</h1>
    <p class="pub-lead">{{ __('Filme-toi et commente un match en direct, montre ton entraînement ou réponds aux questions de tes abonnés. Tout se fait depuis ton téléphone, sans application à installer.') }}</p>

    @if ($refusal)
      <div class="card"><p style="margin:0">{{ $refusal }}</p>
        <p class="meta" style="margin:8px 0 0">{{ __('En attendant, ouvre le salon d’un match pour commenter par écrit avec les autres supporters.') }}</p>
        <a class="btn" href="{{ route('matches') }}" style="align-self:flex-start;margin-top:10px">{{ __('Commenter un match') }}</a></div>
    @else
      <form method="POST" action="{{ route('live.go') }}" class="card">@csrf
        <label for="title">{{ __('Titre de ton live') }}<input id="title" name="title" value="{{ old('title') }}" maxlength="120" required placeholder="{{ __('Ex. : On commente Horoya – Hafia en direct') }}"></label>
        <label for="match"><span>{{ __('Tu commentes un match ?') }} <small class="meta">({{ __('facultatif') }})</small></span>
          <select id="match" name="match"><option value="">{{ __('Non, autre sujet') }}</option>
            @foreach ($matches as $m)<option value="{{ $m['id'] }}" @selected(old('match') === $m['id'])>{{ $m['live'] ? '● ' : '' }}{{ $m['label'] }}</option>@endforeach
          </select></label>
        <div class="rolebox">
          <b>{{ __('Les règles du live') }}</b>
          <ul class="meta" style="margin:0;padding-left:18px;line-height:1.6">
            <li>{{ __('Tu filmes toi-même : ton visage, ton entraînement, ton terrain, ton match amateur.') }}</li>
            <li><b>{{ __('Interdit de filmer une télé, un ordinateur ou une autre diffusion') }}</b> {{ __('(Premier League, Ligue des champions, CAN…) : les droits appartiennent aux chaînes. Le live est coupé et le compte peut être suspendu.') }}</li>
            <li>{{ __('Pas d’insultes, pas de contenu choquant, pas de mineur filmé sans l’accord de ses parents.') }}</li>
          </ul>
          <label class="check"><input type="checkbox" name="rules" value="1" @checked(old('rules')) required> <span>{{ __('J’ai compris et j’accepte les règles du live.') }}</span></label>
        </div>
        @if ($errors->any())<div class="msg err">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        <button class="btn big" type="submit">● {{ __('Préparer mon live') }}</button>
        <p class="meta" style="margin:0">{{ __('À l’étape suivante, tu vois ta caméra et tu lances le direct quand tu es prêt.') }}</p>
      </form>
    @endif
  </div>
</div>
@endsection
