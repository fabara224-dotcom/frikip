@extends('layouts.web')

@section('title', __('Créer mon compte').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:520px">
    <h1>{{ __('Dernière étape') }}</h1>
    @if (session('phone_auth.password_hash'))
      <p class="meta">{{ __('Nouveau compte pour le') }} <b>{{ $phone }}</b> · <a href="{{ route('phone.login') }}">{{ __('Changer de numéro') }}</a></p>
    @else
      <p class="meta">{{ __('Numéro vérifié') }} : {{ $phone }} ✓</p>
    @endif
    <form method="POST" action="{{ route('phone.profile') }}" class="card" id="pf">@csrf
      <div class="seg" role="radiogroup" aria-label="{{ __('Je suis') }}">
        <label><input type="radio" name="role" value="athlete" @checked(old('role', 'athlete') === 'athlete')> {{ __('Sportif') }}</label>
        <label><input type="radio" name="role" value="recruiter" @checked(old('role') === 'recruiter')> {{ __('Recruteur / club') }}</label>
        <label><input type="radio" name="role" value="fan" @checked(old('role') === 'fan')> {{ __('Fan') }}</label>
        <label><input type="radio" name="role" value="proche" @checked(old('role') === 'proche')> {{ __('Un proche') }}</label>
      </div>
      <p class="meta" style="margin:0" data-hint="fan" hidden>{{ __('Tu suis les sportifs, tu aimes, tu commentes et tu votes dans les défis.') }}</p>
      <p class="meta" style="margin:0" data-hint="proche" hidden>{{ __('Tu crées le profil de ton frère, ton fils, un ami… et tu publies ses vidéos pour lui.') }}</p>
      <div class="grid-form procheonly" hidden>
        <label for="relation">{{ __('Tu es son…') }}<select id="relation" name="relation"><option value="">{{ __('Choisir…') }}</option>@foreach (\App\Terrain\Roles::RELATIONS as $r)<option value="{{ $r }}" @selected(old('relation') === $r)>{{ __($r) }}</option>@endforeach</select></label>
        <label for="manager_name">{{ __('Ton nom') }}<input id="manager_name" name="manager_name" value="{{ old('manager_name') }}" maxlength="120"></label>
      </div>
      <label for="name"><span class="lbl-self">{{ __('Nom complet') }}</span><span class="lbl-proche" hidden>{{ __('Nom complet du sportif') }}</span><input id="name" name="name" value="{{ old('name') }}" required></label>
      <div class="athonly">
        <div class="grid-form">
          @if (! \App\Terrain\SportFields::single())<label for="sport">{{ __('Sport') }}<select id="sport" name="sport">@foreach ($sports as $s)<option @selected(old('sport') === $s)>{{ $s }}</option>@endforeach</select></label>@else<input type="hidden" name="sport" value="Football">@endif
          <label for="age">{{ __('Âge') }}<input id="age" name="age" type="number" min="{{ config('terrain.min_age') }}" max="60" value="{{ old('age') }}"></label>
          <label for="country">{{ __('Pays') }}<select id="country" name="country">@foreach ($countries as $c)<option @selected(old('country', session('phone_auth.country', \App\Terrain\Region::viewer())) === $c)>{{ $c }}</option>@endforeach</select></label>
          <label for="city">{{ __('Ville') }}<input id="city" name="city" value="{{ old('city') }}"></label>
        </div>
        <label for="guardian_phone" id="gp">{{ __('Téléphone d’un parent ou tuteur (obligatoire si moins de 18 ans)') }}<input id="guardian_phone" name="guardian_phone" type="tel" value="{{ old('guardian_phone') }}"></label>
      </div>
      @if (session('phone_auth.password_hash'))
        <label for="password_confirmation">{{ __('Retape ton mot de passe') }}<input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></label>
      @endif
      <label class="check"><input type="checkbox" name="terms" value="1" @checked(old('terms'))> {{ __('J’accepte les') }} <a href="{{ route('terms') }}" target="_blank">{{ __('conditions') }}</a> {{ __('et la') }} <a href="{{ route('privacy') }}" target="_blank">{{ __('politique de confidentialité') }}</a>.</label>
      @foreach (['role', 'relation', 'manager_name', 'name', 'sport', 'age', 'country', 'guardian_phone', 'password_confirmation', 'terms'] as $e)@error($e)<p class="field-error" style="margin:0">{{ $message }}</p>@enderror @endforeach
      <button class="btn" type="submit">{{ __('Créer mon compte') }}</button>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var f = document.getElementById('pf'), ath = f.querySelector('.athonly');
  function sync() {
    var r = f.querySelector('input[name=role]:checked').value, a = r === 'athlete' || r === 'proche', p = r === 'proche';
    ath.hidden = !a; ath.querySelectorAll('select,input').forEach(function (i) { i.disabled = !a; });
    var pr = f.querySelector('.procheonly'); pr.hidden = !p; pr.querySelectorAll('select,input').forEach(function (i) { i.disabled = !p; i.required = p; });
    f.querySelector('.lbl-self').hidden = p; f.querySelector('.lbl-proche').hidden = !p;
    f.querySelectorAll('[data-hint]').forEach(function (h) { h.hidden = h.dataset.hint !== r; });
  }
  f.querySelectorAll('input[name=role]').forEach(function (r) { r.addEventListener('change', sync); }); sync();
})();
</script>
@endpush
