{{--
  Questions de l'étape « Ton profil », selon le type de compte choisi (attribut data-types).
  $credentials : demander l'e-mail et le mot de passe (inscription par e-mail).
--}}
@php
  $credentials ??= false;
  $prefillName ??= '';
  $minAge = (int) config('terrain.min_age', 13);
  $help = collect($types)->map(fn ($t) => $t[3]);
@endphp

@foreach ($types as $k => $t)
  <p class="sfhelp" data-types="{{ $k }}" hidden><b>{{ $t[4] }} {{ __($t[1]) }}</b> · {{ __('Avec ce compte :') }} {{ __($t[3]) }}</p>
@endforeach

<div class="grid-form" data-types="proche" hidden>
  <label for="relation">{{ __('Tu es son ou sa…') }}<select id="relation" name="relation">
    <option value="">{{ __('Choisir…') }}</option>
    @foreach (\App\Terrain\Roles::RELATIONS as $r)<option value="{{ $r }}" @selected(old('relation') === $r)>{{ __($r) }}</option>@endforeach
  </select></label>
  <label for="manager_name">{{ __('Ton nom') }}<input id="manager_name" name="manager_name" value="{{ old('manager_name') }}" maxlength="120" autocomplete="name">
    <small class="meta">{{ __('Affiché sur le profil : « profil tenu par … ».') }}</small></label>
</div>

<label for="name">
  <span data-label="joueur">{{ __('Ton nom complet') }}</span>
  <span data-label="proche" hidden>{{ __('Nom complet du joueur') }}</span>
  <span data-label="supporter" hidden>{{ __('Ton nom') }}</span>
  <span data-label="club" hidden>{{ __('Nom du club ou de l’académie') }}</span>
  <span data-label="recruteur" hidden>{{ __('Ton nom complet') }}</span>
  <span data-label="agent" hidden>{{ __('Ton nom complet') }}</span>
  <span data-label="acteur" hidden>{{ __('Ton nom complet') }}</span>
  <input id="name" name="name" value="{{ old('name', $prefillName) }}" required maxlength="120" autocomplete="name">
</label>

@if ($credentials)
  <div class="grid-form">
    <label for="email">{{ __('Adresse e-mail') }}<input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required></label>
    <label for="password">{{ __('Mot de passe') }}<input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
      <small class="meta">{{ __('8 caractères minimum.') }}</small></label>
  </div>
@endif

<div class="grid-form">
  <label for="country">{{ __('Pays') }}<select id="country" name="country" required>@foreach ($countries as $c)<option value="{{ $c }}" @selected(old('country', \App\Terrain\Region::viewer()) === $c)>@td($c)</option>@endforeach</select></label>
  <label for="city"><span>{{ __('Ville') }} <small class="meta">({{ __('facultatif') }})</small></span><input id="city" name="city" value="{{ old('city') }}" placeholder="{{ __('Ex. : Conakry') }}" maxlength="120"></label>
</div>

{{-- Joueur (ou proche qui tient le profil d'un joueur) --}}
<div data-types="joueur proche" hidden class="sfgroup">
  <div class="grid-form">
    <label for="age"><span data-label="joueur">{{ __('Ton âge') }}</span><span data-label="proche" hidden>{{ __('Âge du joueur') }}</span>
      <input id="age" name="age" type="number" inputmode="numeric" min="{{ $minAge }}" max="60" value="{{ old('age') }}" data-age></label>
    <label for="club"><span>{{ __('Club ou académie actuel') }} <small class="meta">({{ __('facultatif') }})</small></span><input id="club" name="club" value="{{ old('club') }}" maxlength="120" placeholder="{{ __('Ex. : Académie de Kipé, sans club') }}"></label>
  </div>
  <b class="sflabel">{{ __('Poste') }}</b>
  <div class="chips pickchips">
    @foreach ($football[0]['options'] as $o)<label class="chip"><input type="radio" name="poste" value="{{ $o }}" @checked(old('poste') === $o)> {{ __($o) }}</label>@endforeach
  </div>
  <b class="sflabel">{{ __('Pied fort') }}</b>
  <div class="chips pickchips">
    @foreach ($football[1]['options'] as $o)<label class="chip"><input type="radio" name="pied" value="{{ $o }}" @checked(old('pied') === $o)> {{ __($o) }}</label>@endforeach
  </div>
  <div id="guardian" hidden class="rolebox">
    <p class="note" style="margin:0">{{ __('Moins de 18 ans : un parent ou tuteur doit valider le compte. Il reçoit un SMS de confirmation. Tant qu’il n’a pas validé, seuls les recruteurs vérifiés peuvent écrire au joueur.') }}</p>
    <label for="guardian_phone">{{ __('Téléphone du parent ou tuteur') }}<input id="guardian_phone" name="guardian_phone" type="tel" value="{{ old('guardian_phone') }}" placeholder="+224 6XX XX XX XX"></label>
  </div>
  <p class="meta" style="margin:0">{{ __('Tu pourras compléter ensuite : matchs joués, buts, passes décisives, parcours, taille, disponibilité pour des essais.') }}</p>
</div>

{{-- Supporter --}}
<div data-types="supporter" hidden class="sfgroup">
  <label for="favorite_club"><span>{{ __('Ton club préféré') }} <small class="meta">({{ __('facultatif') }})</small></span><input id="favorite_club" name="favorite_club" value="{{ old('favorite_club') }}" maxlength="120" placeholder="{{ __('Ex. : Horoya AC, Real Madrid') }}"></label>
</div>

{{-- Club ou académie --}}
<div data-types="club" hidden class="sfgroup">
  <b class="sflabel">{{ __('Type de structure') }}</b>
  <div class="chips pickchips">
    @foreach ($clubTypes as $o)<label class="chip"><input type="radio" name="org_type" value="{{ $o }}" @checked(old('org_type') === $o)> {{ __($o) }}</label>@endforeach
  </div>
  <p class="meta" style="margin:0">{{ __('Après l’inscription : ta page club, tes annonces de détection et la vérification de ta structure.') }}</p>
</div>

{{-- Recruteur ou scout --}}
<div data-types="recruteur" hidden class="sfgroup">
  <label for="organization_r"><span>{{ __('Pour quelle structure recrutes-tu ?') }} <small class="meta">({{ __('facultatif') }})</small></span><input id="organization_r" name="organization" value="{{ old('organization') }}" maxlength="150" placeholder="{{ __('Ex. : FC Kaloum, indépendant') }}"></label>
</div>

{{-- Agent --}}
<div data-types="agent" hidden class="sfgroup">
  <label for="licence_number"><span>{{ __('Numéro de licence d’agent FIFA') }} <small class="meta">({{ __('facultatif maintenant') }})</small></span><input id="licence_number" name="licence_number" value="{{ old('licence_number') }}" maxlength="60" placeholder="{{ __('Ex. : 202X-XXXXX') }}"></label>
  <p class="meta" style="margin:0">{{ __('Nous le vérifions dans le répertoire officiel des agents de la FIFA. Après contrôle, ton profil affiche « Agent FIFA vérifié ».') }}</p>
</div>

{{-- Autre acteur du foot --}}
<div data-types="acteur" hidden class="sfgroup">
  <b class="sflabel">{{ __('Ton métier') }}</b>
  <div class="chips pickchips">
    @foreach ($professions as $o)<label class="chip"><input type="radio" name="profession" value="{{ $o }}" @checked(old('profession') === $o)> {{ __($o) }}</label>@endforeach
  </div>
  <div class="grid-form">
    <label for="age_pro">{{ __('Ton âge') }}<input id="age_pro" name="age" type="number" inputmode="numeric" min="18" max="90" value="{{ old('age') }}"></label>
    <label for="organization_a"><span>{{ __('Où travailles-tu ?') }} <small class="meta">({{ __('facultatif') }})</small></span><input id="organization_a" name="organization" value="{{ old('organization') }}" maxlength="150" placeholder="{{ __('Ex. : Ligue de Conakry, Clinique du sport') }}"></label>
  </div>
</div>

<label class="check" for="terms"><input id="terms" name="terms" type="checkbox" value="1" @checked(old('terms')) required>
  <span>{{ __('J’accepte les') }} <a href="{{ route('terms') }}" target="_blank">{{ __('conditions d’utilisation') }}</a> {{ __('et la') }} <a href="{{ route('privacy') }}" target="_blank">{{ __('politique de confidentialité') }}</a>.</span></label>

@if ($errors->any())
  <div class="msg err" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif
