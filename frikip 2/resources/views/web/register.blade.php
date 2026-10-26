@extends('layouts.web')

@section('title', 'Créer un compte – '.config('terrain.app_name'))

@php($role = old('role', request('role', 'athlete')))

@section('content')
<div class="page gut">
  <div class="narrow" style="max-width:640px">
    <h1>Créer un compte</h1>
    <a class="btn phonebtn" href="{{ route('phone.login') }}"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/></svg> {{ __('Continuer avec mon numéro de téléphone') }}</a>
    <p class="meta" style="text-align:center;margin:6px 0 12px">{{ __('ou avec un e-mail') }}</p>
    @unless ($realAccounts ?? false)<p class="meta">Le même compte fonctionne sur le site et dans l'application.</p>@endunless

    <form method="POST" action="{{ route('register') }}" class="card" id="reg">@csrf
      <div class="plans">
        <label class="opt"><input type="radio" name="role" value="athlete" @checked($role === 'athlete')><b>Je suis sportif</b><span class="meta">Je publie mes compilations</span></label>
        <label class="opt"><input type="radio" name="role" value="recruiter" @checked($role === 'recruiter')><b>Je recrute</b><span class="meta">Club, académie, agent, université</span></label>
      </div>

      <label for="name"><span data-label-athlete>Nom complet</span><span data-label-recruiter hidden>Nom du club ou de la structure</span>
        <input id="name" name="name" value="{{ old('name') }}" required></label>
      <label for="email">E-mail<input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required></label>
      <label for="password">Mot de passe (8 caractères minimum)<input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required></label>
      <label for="country">Pays<select id="country" name="country" required>@foreach ($countries as $c)<option @selected(old('country', \App\Terrain\Region::viewer()) === $c)>{{ $c }}</option>@endforeach</select></label>

      <div data-athlete style="display:flex;flex-direction:column;gap:12px">
        <div class="grid-form">
          <label for="age">Âge<input id="age" name="age" type="number" min="{{ config('terrain.min_age') }}" max="60" value="{{ old('age') }}"></label>
          <label for="city">Ville<input id="city" name="city" value="{{ old('city') }}" placeholder="Ex. : Conakry"></label>
        </div>

        <div id="guardian" hidden>
          <p class="note" style="margin:0 0 8px">Tu as moins de 18 ans : un parent ou tuteur doit valider ton compte. Il recevra un SMS de confirmation.</p>
          <label for="guardian_phone">Téléphone du parent ou tuteur<input id="guardian_phone" name="guardian_phone" type="tel" value="{{ old('guardian_phone') }}" placeholder="+224 6XX XX XX XX"></label>
        </div>

        <b>Ton sport</b>
        <div class="sport-grid">
          @foreach ($sports as $s => $fields)
            @php($c = \App\Terrain\SportFields::color($s))
            <label class="opt" style="flex-direction:row;align-items:center;gap:8px"><input type="radio" name="sport" value="{{ $s }}" @checked(old('sport') === $s)><span style="width:12px;height:12px;border-radius:50%;background:{{ $c[0] }}"></span><b style="font-size:19px">{{ $s }}</b></label>
          @endforeach
        </div>

        @foreach ($sports as $s => $fields)
          <fieldset class="card" data-sport="{{ $s }}" hidden style="border-style:dashed">
            <legend style="padding:0 6px;font-weight:700">Fiche {{ mb_strtolower($s) }}</legend>
            @foreach ($fields as $i => $f)
              @php($id = 'f-'.\Illuminate\Support\Str::slug($s).'-'.$i)
              <label for="{{ $id }}">{{ $f['label'] }}
                @if ($f['type'] === 'choice')
                  <select id="{{ $id }}" name="fields[{{ $f['label'] }}]" disabled>@foreach ($f['options'] as $o)<option @selected(old('fields.'.$f['label']) === $o)>{{ $o }}</option>@endforeach</select>
                @else
                  <input id="{{ $id }}" name="fields[{{ $f['label'] }}]" type="{{ $f['type'] === 'number' ? 'number' : 'text' }}" value="{{ old('fields.'.$f['label']) }}" disabled>
                @endif
              </label>
            @endforeach
          </fieldset>
        @endforeach
      </div>

      <label class="check" for="terms"><input id="terms" name="terms" type="checkbox" value="1" @checked(old('terms')) required>
        <span>J'accepte les <a href="{{ route('terms') }}" target="_blank">conditions d'utilisation</a> et la <a href="{{ route('privacy') }}" target="_blank">politique de confidentialité</a>.</span></label>

      @if ($errors->any())
        <div class="msg err">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
      @endif
      <button class="btn" type="submit">Créer mon compte</button>
    </form>
    <p>Déjà un compte ? <a href="{{ route('login') }}">Se connecter</a></p>
  </div>
</div>
@endsection

@push('scripts')
<script>
  (function () {
    var form = document.getElementById('reg');
    function sync() {
      var role = (form.querySelector('[name=role]:checked') || {}).value;
      var athlete = role !== 'recruiter';
      form.querySelector('[data-athlete]').hidden = !athlete;
      form.querySelector('[data-label-athlete]').hidden = !athlete;
      form.querySelector('[data-label-recruiter]').hidden = athlete;
      ['age'].forEach(function (n) { form.querySelector('[name=' + n + ']').required = athlete; });
      var sport = (form.querySelector('[name=sport]:checked') || {}).value;
      form.querySelectorAll('[data-sport]').forEach(function (fs) {
        var on = athlete && fs.dataset.sport === sport;
        fs.hidden = !on;
        fs.querySelectorAll('input,select').forEach(function (el) { el.disabled = !on; });
      });
      var age = parseInt(form.querySelector('[name=age]').value, 10);
      var minor = athlete && age > 0 && age < 18;
      document.getElementById('guardian').hidden = !minor;
      document.getElementById('guardian_phone').required = minor;
    }
    form.addEventListener('change', sync);
    form.addEventListener('input', sync);
    sync();
  })();
</script>
@endpush
