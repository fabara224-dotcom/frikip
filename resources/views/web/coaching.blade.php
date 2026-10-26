@extends('layouts.web')

@section('title', __('Coaching').' – '.config('terrain.app_name'))
@section('description', __('Un programme d’entraînement gratuit selon ton poste et tes chiffres, et un coach pour te suivre.'))

@php
  $curLabel = $cur === 'XOF' ? 'FCFA' : $cur;
@endphp

@section('content')
<div class="page gut">
  @include('web.partials.defi-coach-switch', ['on' => 'coaching'])
  <div class="offer-head">
    <h1>{{ __('Ton prochain cap commence ici.') }}</h1>
    <p class="pub-lead">{{ __('Donne tes chiffres de la saison passée : tu reçois tout de suite ton diagnostic et un programme de 4 semaines, gratuit. Pour aller plus loin, un coach te suit.') }}</p>
    @if ($mine)
      <a class="probanner" href="{{ route('coaching.thread', $mine->reference) }}"><span><b>{{ __('Ton suivi :p', ['p' => __($mine->package_label)]) }}</b>{{ __(\App\Terrain\Growth\Coaching::STATUS[$mine->status] ?? $mine->status) }}</span><span class="btn sm">{{ __('Ouvrir') }}</span></a>
    @elseif ($hasPlan)
      <p><a href="{{ route('coaching.plan.show') }}">{{ __('Revoir mon dernier programme') }}</a></p>
    @endif
  </div>

  <div class="coachgrid">
    <form method="POST" action="{{ route('coaching.plan') }}" class="card coachform" id="plan">@csrf
      <h2>{{ __('Mon programme gratuit') }}</h2>
      <div class="phonerow">
        <label for="sport">{{ __('Sport') }}
          <select id="sport" name="sport" onchange="location.href='{{ route('coaching') }}?sport='+encodeURIComponent(this.value)+'#plan'">
            @foreach ($sports as $s)<option value="{{ $s }}" @selected($s === $sport)>@td($s)</option>@endforeach
          </select></label>
        @if ($positions)
          <label for="position">{{ __('Poste') }}
            <select id="position" name="position" onchange="location.href='{{ route('coaching') }}?sport='+encodeURIComponent(this.form.sport.value)+'&poste='+encodeURIComponent(this.value)+'#plan'">
              <option value="">{{ __('Choisir…') }}</option>
              @foreach ($positions as $p)<option value="{{ $p }}" @selected($p === $position)>@td($p)</option>@endforeach
            </select></label>
        @endif
      </div>

      <fieldset class="statset"><legend>{{ __('Ta saison passée') }}</legend>
        @foreach ($fields as $k => [$label, $help])
          <label for="st-{{ $k }}">{{ __($label) }}@if ($help)<small>{{ __($help) }}</small>@endif
            <input id="st-{{ $k }}" name="stats[{{ $k }}]" type="number" inputmode="decimal" min="0" step="any" value="{{ old('stats.'.$k, $stats[$k] ?? '') }}"></label>
        @endforeach
      </fieldset>
      @error('stats.*')<p class="field-error">{{ $message }}</p>@enderror

      <fieldset class="rates"><legend>{{ __('Comment tu te notes ? (1 = à travailler, 5 = point fort)') }}</legend>
        @foreach (\App\Terrain\Growth\Coaching::QUALITIES as $q => $ql)
          <div class="rate"><span>{{ __($ql) }}</span>
            <span class="ratebtns">@for ($n = 1; $n <= 5; $n++)<label><input type="radio" name="notes[{{ $q }}]" value="{{ $n }}" @checked((int) ($ratings[$q] ?? 0) === $n)><span>{{ $n }}</span></label>@endfor</span></div>
        @endforeach
      </fieldset>

      <label for="goal">{{ __('Ton objectif') }}
        <select id="goal" name="goal"><option value="">{{ __('Choisir…') }}</option>
          @foreach (\App\Terrain\Growth\Coaching::GOALS as $g)<option value="{{ $g }}" @selected($goal === $g)>{{ __($g) }}</option>@endforeach
        </select></label>
      <button class="btn big" type="submit">{{ __('Voir mon programme') }}</button>
      @unless ($me)<p class="meta" style="margin:0">{{ __('Sans compte, ton programme n’est pas enregistré.') }} <a href="{{ route('phone.login') }}">{{ __('Créer un compte') }}</a></p>@endunless
    </form>

    <div class="coachside">
      <h2>{{ __('Un coach pour te suivre') }}</h2>
      <p class="meta" style="margin-top:-6px">{{ __('Tu écris tes questions et tes demandes, le coach te répond avec un programme adapté et des bilans réguliers.') }}</p>
      <div class="pricegrid onecol">
        @foreach ($offers as $i => [$label, $price, $days, $note])
          <div class="price {{ $i === 1 ? 'best' : '' }}">
            @if ($i === 1)<span class="pill pro">{{ __('Le plus choisi') }}</span>@endif
            <b>{{ __($label) }}</b>
            <span class="amount">{{ number_format($price, 0, ',', ' ') }} <small>{{ $curLabel }}</small></span>
            <span class="meta">{{ __($note) }}</span>
            <a class="btn {{ $i === 1 ? '' : 'ghost' }}" href="{{ $me ? route('coaching.reserve', $i) : route('login') }}">{{ __('Réserver') }}</a>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</div>
@endsection
