@extends('layouts.web')

@section('title', __('Devenir partenaire').' – '.config('terrain.app_name'))

@section('content')
<div class="page gut">
  <div class="cols">
    <div>
      <h1>{{ __('Devenir partenaire') }}</h1>
      <p>{{ __('Clubs, académies, écoles, universités, fédérations, médias sportifs et entreprises : faisons grandir les talents ensemble.') }}</p>
      <div class="grid2" style="margin-top:12px">
        <div class="card"><b>🏟️ {{ __('Clubs et académies') }}</b><span class="meta">{{ __('Accès Premium gratuit pendant le lancement, page vérifiée, journées de détection organisées avec nous.') }}</span></div>
        <div class="card"><b>🎓 {{ __('Écoles et universités') }}</b><span class="meta">{{ __('Repérez les sportifs pour vos bourses ; diffusion de vos offres d’études.') }}</span></div>
        <div class="card"><b>📻 {{ __('Médias sportifs') }}</b><span class="meta">{{ __('Le joueur de la semaine et les classements pour vos émissions et pages Facebook.') }}</span></div>
        <div class="card"><b>🤝 {{ __('Fédérations et entreprises') }}</b><span class="meta">{{ __('Tournois sponsorisés, détections nationales, équipements pour les lauréats.') }}</span></div>
      </div>
    </div>
    <aside>
      <form method="POST" action="{{ route('partners.store') }}" class="card">@csrf
        <h3 style="margin:0">{{ __('Nous contacter') }}</h3>
        <label for="type">{{ __('Vous êtes') }}<select id="type" name="type">
          @foreach (['club' => __('Club'), 'academie' => __('Académie'), 'ecole' => __('École'), 'universite' => __('Université'), 'federation' => __('Fédération'), 'media' => __('Média (radio, page Facebook…)'), 'entreprise' => __('Entreprise / sponsor'), 'autre' => __('Autre')] as $k => $l)
            <option value="{{ $k }}" @selected(old('type') === $k)>{{ $l }}</option>
          @endforeach
        </select></label>
        <label for="organization">{{ __('Nom de la structure') }}<input id="organization" name="organization" value="{{ old('organization') }}" required></label>
        <label for="contact_name">{{ __('Votre nom') }}<input id="contact_name" name="contact_name" value="{{ old('contact_name') }}" required></label>
        <label for="phone">{{ __('Téléphone / WhatsApp') }}<input id="phone" name="phone" type="tel" value="{{ old('phone') }}"></label>
        <label for="email">{{ __('E-mail') }}<input id="email" name="email" type="email" value="{{ old('email') }}"></label>
        <label for="message">{{ __('Votre projet') }}<textarea id="message" name="message" rows="4">{{ old('message') }}</textarea></label>
        @foreach (['organization', 'contact_name', 'phone', 'email'] as $e)@error($e)<p class="field-error" style="margin:0">{{ $message }}</p>@enderror @endforeach
        <button class="btn" type="submit">{{ __('Envoyer') }}</button>
      </form>
    </aside>
  </div>
  <x-fabara for="tournoi" />
</div>
@endsection
