@extends('layouts.web')

@section('title', __('Ma page club').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:640px">
    <h1>{{ $o ? __('Modifier ma page') : __('Créer la page de ma structure') }}</h1>
    <p class="meta">{{ __('Ta page présente ton club, tes détections et tes offres. Le badge vérifié est ajouté après contrôle de ta licence.') }}</p>
    <form method="POST" action="{{ route('clubs.edit') }}" class="card">@csrf
      <label for="name">{{ __('Nom') }}<input id="name" name="name" value="{{ old('name', $o->name ?? $me['name']) }}" required></label>
      <div class="grid-form">
        <label for="type">{{ __('Type') }}<select id="type" name="type">@foreach ($types as $k => $l)<option value="{{ $k }}" @selected(old('type', $o->type ?? 'club') === $k)>{{ __($l) }}</option>@endforeach</select></label>
        <label for="sport">{{ __('Sport principal') }}<select id="sport" name="sport">@foreach ($sports as $s)<option @selected(old('sport', $o->sport ?? 'Football') === $s)>{{ $s }}</option>@endforeach</select></label>
        <label for="city">{{ __('Ville') }}<input id="city" name="city" value="{{ old('city', $o->city ?? 'Conakry') }}" required></label>
        <label for="country">{{ __('Pays') }}<select id="country" name="country">@foreach (\App\Terrain\Region::names() as $c)<option @selected(old('country', $o->country ?? \App\Terrain\Region::viewer()) === $c)>{{ $c }}</option>@endforeach</select></label>
      </div>
      <label for="description">{{ __('Présentation') }}<textarea id="description" name="description" rows="4">{{ old('description', $o->description ?? '') }}</textarea></label>
      <label for="palmares">{{ __('Palmarès') }}<input id="palmares" name="palmares" value="{{ old('palmares', $o->palmares ?? '') }}"></label>
      <div class="grid-form">
        <label for="licence">{{ __('N° de licence / affiliation') }}<input id="licence" name="licence" value="{{ old('licence', $o->licence ?? '') }}"></label>
        <label for="color">{{ __('Couleur') }}<input id="color" name="color" type="color" value="{{ old('color', $o->color ?? '#1F7A4A') }}"></label>
      </div>
      @foreach (['name', 'city', 'country', 'color'] as $e)@error($e)<p class="field-error" style="margin:0">{{ $message }}</p>@enderror @endforeach
      <button class="btn" type="submit">{{ __('Enregistrer') }}</button>
    </form>
    @if ($o)
      <div class="card" style="margin-top:14px">
        <b>{{ __('Organiser une journée de détection') }}</b>
        <form method="POST" action="{{ route('events.store') }}" style="display:flex;flex-direction:column;gap:10px">@csrf
          <label for="e-title">{{ __('Titre') }}<input id="e-title" name="title" placeholder="{{ __('Ex. : Détection U17 – milieux') }}" required></label>
          <div class="grid-form">
            <label for="e-sport">{{ __('Sport') }}<select id="e-sport" name="sport">@foreach ($sports as $s)<option @selected($o->sport === $s)>{{ $s }}</option>@endforeach</select></label>
            <label for="e-date">{{ __('Date') }}<input id="e-date" name="date" type="date" min="{{ today()->toDateString() }}" required></label>
            <label for="e-time">{{ __('Heure') }}<input id="e-time" name="time" type="time" value="09:00"></label>
            <label for="e-cap">{{ __('Places') }}<input id="e-cap" name="capacity" type="number" min="1" value="50" required></label>
            <label for="e-min">{{ __('Âge min.') }}<input id="e-min" name="age_min" type="number" min="8" max="40"></label>
            <label for="e-max">{{ __('Âge max.') }}<input id="e-max" name="age_max" type="number" min="8" max="45"></label>
          </div>
          <label for="e-place">{{ __('Lieu (stade, terrain)') }}<input id="e-place" name="place" required></label>
          <label for="e-city">{{ __('Ville') }}<input id="e-city" name="city" value="{{ $o->city }}" required></label>
          <label for="e-desc">{{ __('À apporter, déroulé…') }}<textarea id="e-desc" name="description" rows="3"></textarea></label>
          <button class="btn" type="submit">{{ __('Publier la détection') }}</button>
          <p class="meta" style="margin:0">{{ __('Interdit de demander de l’argent aux participants.') }}</p>
        </form>
      </div>
    @endif
  </div>
</div>
@endsection
