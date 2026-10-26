@extends('layouts.web')

@section('title', __('Mon programme').' – '.config('terrain.app_name'))

@php
  $levels = ['tres_bien' => [__('Très bonne saison'), 'ok'], 'correct' => [__('Bonne base'), 'pro'], 'a_travailler' => [__('À travailler en priorité'), 'warn']];
  [$lvLabel, $lvClass] = $levels[$plan['level']] ?? $levels['correct'];
@endphp

@section('content')
<div class="page gut">
  <div class="narrow plan">
    <p class="meta" style="margin:0"><a href="{{ route('coaching') }}">← {{ __('Coaching') }}</a></p>
    <h1>{{ __('Ton programme de 4 semaines') }}</h1>
    <p class="plan-who">@td($plan['sport'])@if ($plan['position']) · @td($plan['position'])@endif @if ($plan['goal']) · {{ __($plan['goal']) }}@endif <span class="pill {{ $lvClass }}">{{ $lvLabel }}</span></p>

    <section class="plan-diag">
      <p class="plan-sum">{{ __($plan['summary']) }}</p>
      @if ($plan['findings'])<ul class="plan-list">@foreach ($plan['findings'] as $f)<li>{{ __($f) }}</li>@endforeach</ul>@endif
      @if ($plan['good'])<ul class="plan-list good">@foreach ($plan['good'] as $f)<li>{{ __($f) }}</li>@endforeach</ul>@endif
    </section>

    <h2>{{ __('Tes 3 priorités') }}</h2>
    <ol class="prio">
      @foreach ($plan['priorities'] as $p)<li><b>{{ __($p['title']) }}</b><span>{{ __('Pour mesurer ton progrès') }} : {{ __($p['test']) }}</span></li>@endforeach
    </ol>

    <h2>{{ __('Chaque semaine : 3 séances') }}</h2>
    @foreach ($plan['sessions'] as [$name, $theme, $exercises])
      <section class="session">
        <h3>{{ __($name) }} <span>{{ is_array($theme) ? __($theme[0]).' + '.mb_strtolower(__($theme[1])) : __($theme) }}</span></h3>
        @foreach ($exercises as [$ex, $dose, $how])
          <div class="exo"><b>{{ __($ex) }}</b><em>{{ __($dose) }}</em><p>{{ __($how) }}</p></div>
        @endforeach
      </section>
    @endforeach

    <ul class="plan-list weeks">@foreach ($plan['weeks'] as $w)<li>{{ __($w) }}</li>@endforeach</ul>

    <div class="coach-cta">
      <div><b>{{ __('Tu veux un coach qui ajuste ce programme avec toi ?') }}</b>
        <span>{{ __('Il regarde tes vidéos, répond à tes questions et te fait un bilan chaque semaine.') }}</span></div>
      <a class="btn" href="{{ $me ? route('coaching.reserve', 1) : route('login') }}">{{ __('Être suivi par un coach') }}</a>
    </div>
    <p class="meta">{{ __('Ces conseils ne remplacent pas un avis médical. En cas de douleur, arrête l’exercice et consulte.') }}</p>
  </div>
</div>
@endsection
