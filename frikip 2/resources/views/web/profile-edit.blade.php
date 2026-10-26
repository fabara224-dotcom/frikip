@extends('layouts.web')

@section('title', __('Modifier mon profil').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:640px">
    <h1>{{ __('Modifier mon profil') }}</h1>
    @if (session('status'))<p class="note">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('profile.edit') }}" class="card">@csrf
      <label for="name">{{ $a ? __('Nom complet') : __('Nom du club ou de la structure') }}<input id="name" name="name" value="{{ old('name', $u['name']) }}" required maxlength="120"></label>
      @if ($a)
        <div class="grid-form">
          <label for="age">{{ __('Âge') }}<input id="age" name="age" type="number" min="{{ config('terrain.min_age') }}" max="60" value="{{ old('age', $a['age']) }}" required></label>
          <label for="country">{{ __('Pays') }}<select id="country" name="country">@foreach ($countries as $c)<option @selected(old('country', $a['country']) === $c)>{{ $c }}</option>@endforeach</select></label>
          <label for="city">{{ __('Ville') }}<input id="city" name="city" value="{{ old('city', $a['city'] === '–' ? '' : $a['city']) }}"></label>
          <label for="height">{{ __('Taille') }}<input id="height" name="height" value="{{ old('height', $a['height'] === '–' ? '' : $a['height']) }}" placeholder="1,80 m"></label>
          <label for="weight">{{ __('Poids') }}<input id="weight" name="weight" value="{{ old('weight', $a['weight'] === '–' ? '' : $a['weight']) }}" placeholder="72 kg"></label>
          <label for="level">{{ __('Niveau') }}<select id="level" name="level">@foreach (['Amateur', 'Semi-pro', 'Professionnel', 'Universitaire', 'Jeune espoir'] as $l)<option @selected(old('level', $a['level']) === $l)>{{ $l }}</option>@endforeach</select></label>
        </div>
        <label for="club">{{ __('Club actuel') }}<input id="club" name="club" value="{{ old('club', $a['club'] === 'Sans club' ? '' : $a['club']) }}"></label>
        <label for="availability">{{ __('Disponibilité') }}<input id="availability" name="availability" value="{{ old('availability', $a['availability']) }}" placeholder="{{ __('Immédiate, juillet 2027…') }}"></label>
        <label for="specialty">{{ __('Point fort') }}<input id="specialty" name="specialty" maxlength="80" value="{{ old('specialty', $a['specialty'] ?? '') }}" placeholder="{{ __('Ex. : Dribble et vitesse') }}"></label>
        @if ($a['age'] < 18 || old('age', 99) < 18)
          <label for="guardian_phone">{{ __('Téléphone du parent ou tuteur') }}<input id="guardian_phone" name="guardian_phone" type="tel" value="{{ old('guardian_phone', $a['guardian_phone'] ?? '') }}"></label>
        @endif
        <fieldset class="langfs"><legend><b>{{ __('Fiche') }} {{ mb_strtolower($a['sport']) }}</b></legend>
          @foreach ($sports[$a['sport']] ?? [] as $i => $f)
            @php($val = old('fields.'.$f['label'], $a['fields'][$f['label']] ?? ''))
            <label for="f{{ $i }}">{{ $f['label'] }}
              @if ($f['type'] === 'choice')
                <select id="f{{ $i }}" name="fields[{{ $f['label'] }}]">@foreach ($f['options'] as $o)<option @selected($val === $o)>{{ $o }}</option>@endforeach</select>
              @else
                <input id="f{{ $i }}" name="fields[{{ $f['label'] }}]" type="{{ $f['type'] === 'number' ? 'number' : 'text' }}" value="{{ $val === '–' ? '' : $val }}">
              @endif
            </label>
          @endforeach
        </fieldset>
      @else
        <label for="org_type">{{ __('Type de structure') }}<select id="org_type" name="org_type">@foreach ($orgTypes as $t)<option @selected(old('org_type', $u['org_type'] ?? '') === $t)>{{ $t }}</option>@endforeach</select></label>
        <label for="country">{{ __('Pays') }}<select id="country" name="country">@foreach ($countries as $c)<option @selected(old('country', \App\Terrain\Region::viewer()) === $c)>{{ $c }}</option>@endforeach</select></label>
      @endif
      @if ($errors->any() && ! $errors->has('current') && ! $errors->has('password'))<div class="msg err">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
      <button class="btn" type="submit">{{ __('Enregistrer') }}</button>
    </form>

    <h2 style="margin-top:28px">{{ __('Mot de passe') }}</h2>
    <form method="POST" action="{{ route('profile.password') }}" class="card">@csrf
      @if ($u['email'])
        <label for="current">{{ __('Mot de passe actuel') }}<input id="current" name="current" type="password" autocomplete="current-password"></label>
      @else
        <p class="meta" style="margin:0">{{ __('Tu te connectes avec ton numéro. Tu peux aussi choisir un mot de passe.') }}</p>
      @endif
      <label for="password">{{ __('Nouveau mot de passe (8 caractères minimum)') }}<input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required></label>
      <label for="password_confirmation">{{ __('Confirme le nouveau mot de passe') }}<input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></label>
      @if ($errors->has('current') || $errors->has('password'))<p class="field-error" style="margin:0">{{ $errors->first('current') ?: $errors->first('password') }}</p>@endif
      <button class="btn ghost" type="submit">{{ __('Changer le mot de passe') }}</button>
    </form>
  </div>
</div>
@endsection
