@extends('layouts.web')

@section('title', __('Réserver un coach').' – '.config('terrain.app_name'))

@php
  [$label, $price, $days, $note] = $offer;
@endphp

@section('content')
<div class="page gut">
  <div class="narrow pub">
    <p class="meta" style="margin:0"><a href="{{ route('coaching') }}">← {{ __('Coaching') }}</a></p>
    <h1>{{ __($label) }}</h1>
    <p class="pub-lead">{{ __($note) }} <b>{{ \App\Terrain\Region::format($price, $cur) }}</b></p>

    @if ($closed)
      <p class="note">{{ $closed }} <a href="{{ route('support') }}">{{ __('Aide et contact') }}</a></p>
    @else
    <form method="POST" action="{{ route('coaching.store') }}" class="card">@csrf
      <input type="hidden" name="package" value="{{ $package }}">
      <label for="goal">{{ __('Ton objectif') }}
        <select id="goal" name="goal" required>
          @foreach ($goals as $g)<option value="{{ $g }}" @selected(old('goal', $plan['goal'] ?? null) === $g)>{{ __($g) }}</option>@endforeach
        </select></label>
      @error('goal')<p class="field-error">{{ $message }}</p>@enderror
      <label for="details">{{ __('Ta demande au coach') }}
        <textarea id="details" name="details" rows="5" maxlength="2000" required placeholder="{{ __('Ex. : attaquant, 3 buts la saison passée. Je rate beaucoup de face-à-face et je fatigue après 60 minutes. Je veux être prêt pour un essai en janvier.') }}">{{ old('details') }}</textarea></label>
      @error('details')<p class="field-error">{{ $message }}</p>@enderror
      @if ($plan)<p class="meta" style="margin:0">{{ __('Le coach verra aussi ton programme gratuit et tes chiffres.') }}</p>@endif
      <label for="whatsapp">{{ __('Ton numéro WhatsApp') }}<input id="whatsapp" name="whatsapp" type="tel" value="{{ old('whatsapp', $phone) }}" placeholder="{{ __('Numéro avec ou sans l’indicatif +:d', ['d' => \App\Terrain\Region::dial($country)]) }}" required></label>
      @error('whatsapp')<p class="field-error">{{ $message }}</p>@enderror
      <b>{{ __('Paiement') }}</b>
      <div class="plans">
        @foreach (['om' => ['Orange Money', '#FF7900'], 'momo' => ['MTN MoMo', '#FFCB05'], 'card' => [__('Carte bancaire'), '#0B7A4B']] as $k => [$name, $color])
          <label class="opt" style="flex-direction:row;align-items:center;gap:10px"><input type="radio" name="method" value="{{ $k }}" @checked(old('method', 'om') === $k)><span style="width:20px;height:20px;border-radius:50%;background:{{ $color }}"></span>{{ $name }}</label>
        @endforeach
      </div>
      <button class="btn big" type="submit">{{ __('Réserver et payer') }}</button>
      <p class="meta" style="margin:0">{{ __('Le numéro WhatsApp sert aussi au paiement Mobile Money.') }}</p>
    </form>
    @endif
  </div>
</div>
@endsection
