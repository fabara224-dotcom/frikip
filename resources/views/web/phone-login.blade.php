@extends('layouts.web')

@section('title', __('Connexion par téléphone').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:440px">
    <h1>{{ __('Entrer avec mon numéro') }}</h1>
    <p class="meta">{{ __('Pas besoin d’e-mail : tu reçois un code par SMS. Nouveau numéro = nouveau compte en 30 secondes.') }}</p>

    @if (! $phone || $errors->has('phone'))
      <form method="POST" action="{{ route('phone.send') }}" class="card">@csrf
        <div class="phonerow">
          <label for="pays">{{ __('Pays') }}<select id="pays" name="country">
            @foreach (\App\Terrain\Region::COUNTRIES as $c => $info)<option value="{{ $c }}" @selected(old('country', \App\Terrain\Region::viewer()) === $c)>{{ \App\Terrain\DataLang::t($c) }} (+{{ $info[1] }})</option>@endforeach
          </select></label>
          <label for="phone">{{ __('Numéro de téléphone') }}<input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" required autofocus></label>
        </div>
        <span class="meta" style="font-size:12.5px">{{ __('L’indicatif du pays choisi est ajouté automatiquement.') }}</span>
        @error('phone')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
        <button class="btn" type="submit">{{ __('Recevoir mon code') }}</button>
      </form>
    @else
      <form method="POST" action="{{ route('phone.verify') }}" class="card">@csrf
        <p style="margin:0">{{ __('Code envoyé au') }} <b>{{ $phone }}</b></p>
        @if ($demoCode)<p class="note" style="margin:0">{{ __('Mode démo (aucun SMS configuré) : ton code est') }} <b style="font-family:var(--f-mono);font-size:18px">{{ $demoCode }}</b></p>@endif
        <label for="code">{{ __('Code à 6 chiffres') }}<input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus></label>
        @error('code')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
        <button class="btn" type="submit">{{ __('Valider') }}</button>
      </form>
      <form method="POST" action="{{ route('phone.send') }}" style="margin-top:10px">@csrf<input type="hidden" name="phone" value="{{ $phone }}"><button class="linkbtn" type="submit">{{ __('Renvoyer le code') }}</button> · <a href="{{ route('phone.login', ['changer' => 1]) }}">{{ __('Changer de numéro') }}</a></form>
    @endif
    <p class="meta" style="margin-top:16px">{{ __('Tu préfères l’e-mail ?') }} <a href="{{ route('login') }}">{{ __('Connexion avec e-mail') }}</a></p>
  </div>
</div>
@endsection
