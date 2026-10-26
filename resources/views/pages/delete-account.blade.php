@extends('layouts.app')

@section('title', 'Supprimer mon compte – '.config('terrain.app_name'))
@section('description', 'Demander la suppression de votre compte '.config('terrain.app_name').' et de vos données, avec ou sans l\'application.')

@section('content')
<div class="doc gut">
<article class="narrow">
  <span class="eyebrow">Suppression de compte</span>
  <h1>Supprimer votre compte <x-cfg key="app_name" /></h1>
  <p class="lead">Vous pouvez supprimer votre compte <x-cfg key="app_name" /> (édité par <x-cfg key="editor" />) et les données associées à tout moment, gratuitement, avec ou sans l'application.</p>

  <h2 id="app">Option 1 · Dans l'application</h2>
  <ol>
    <li>Ouvrez <x-cfg key="app_name" /> et touchez l'onglet <b>Profil</b> (ou <b>Compte</b> pour les recruteurs).</li>
    <li>Touchez <b>Paramètres et confidentialité</b>.</li>
    <li>Touchez <b>Supprimer mon compte</b>, puis confirmez.</li>
  </ol>

  <h2 id="web">Option 2 · Ici, avec vos identifiants</h2>
  <div class="card">
    @if (session('deleted'))
      <div class="msg ok" role="status">{{ session('deleted') }}</div>
    @elseif (! $webDeletion)
      <p class="meta" style="margin:0">La suppression en ligne n'est pas encore activée sur ce site. Utilisez l'option 1 ou l'option 3.</p>
    @elseif ($connectedEmail)
      <p style="margin:0">Connecté en tant que <b>{{ $connectedEmail }}</b>.</p>
      <p style="margin:0">La suppression est <b>définitive</b> : profil, vidéos, messages, candidatures et favoris seront effacés.</p>
      <form method="POST" action="{{ route('deletion.confirm') }}" class="card" style="padding:0;border:0">
        @csrf
        <label for="confirmation">Pour confirmer, écrivez SUPPRIMER
          <input id="confirmation" name="confirmation" type="text" autocomplete="off" required>
        </label>
        @error('confirmation', 'confirm')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
        <div class="row">
          <button class="btn danger" type="submit">Supprimer définitivement mon compte</button>
        </div>
      </form>
      <form method="POST" action="{{ route('deletion.cancel') }}">
        @csrf
        <button class="btn ghost" type="submit">Annuler et me déconnecter</button>
      </form>
    @else
      <form method="POST" action="{{ route('deletion.login') }}" class="card" style="padding:0;border:0">
        @csrf
        <label for="email">E-mail du compte
          <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required>
        </label>
        <label for="password">Mot de passe
          <input id="password" name="password" type="password" autocomplete="current-password" required>
        </label>
        @if ($errors->login->any())<p class="field-error" style="margin:0">{{ $errors->login->first() }}</p>@endif
        <div class="row"><button class="btn" type="submit">Me connecter</button></div>
        <p class="meta" style="margin:0">Vos identifiants servent uniquement à confirmer que le compte vous appartient. Ils ne sont pas enregistrés.</p>
      </form>
    @endif
  </div>

  <h2 id="demande">Option 3 · Sans vous connecter</h2>
  <p>Mot de passe oublié, application désinstallée ou téléphone perdu ? Envoyez une demande. Nous vérifions que le compte vous appartient (code envoyé par e-mail ou SMS), puis nous le supprimons <b>sous 7 jours</b> et vous confirmons la suppression.</p>
  @if (session('requested'))
    <div class="msg ok" role="status">Demande reçue. Nous vous contactons pour vérifier le compte, puis nous le supprimons sous 7 jours.</div>
  @else
    <form method="POST" action="{{ route('deletion.request') }}" class="card">
      @csrf
      <label for="contact">E-mail ou numéro de téléphone du compte
        <input id="contact" name="contact" type="text" value="{{ old('contact') }}" required>
      </label>
      @error('contact')<p class="field-error" style="margin:0">{{ $message }}</p>@enderror
      <label for="profile_name">Nom sur le profil
        <input id="profile_name" name="profile_name" type="text" value="{{ old('profile_name') }}">
      </label>
      <label for="scope">Que voulez-vous supprimer ?
        <select id="scope" name="scope">
          <option value="account" @selected(old('scope', 'account') === 'account')>Tout mon compte et mes données</option>
          <option value="activity" @selected(old('scope') === 'activity')>Seulement mon historique d'activité</option>
          <option value="videos" @selected(old('scope') === 'videos')>Seulement certaines vidéos</option>
        </select>
      </label>
      <label for="note">Précisions (facultatif)
        <textarea id="note" name="note" rows="3">{{ old('note') }}</textarea>
      </label>
      <div class="hp" aria-hidden="true"><label for="website">Ne pas remplir<input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
      <div class="row"><button class="btn" type="submit">Envoyer ma demande</button></div>
    </form>
  @endif
  <p>Vous pouvez aussi écrire à <x-cfg key="privacy_email" mailto /> avec pour objet « Suppression de compte ».</p>

  <h2 id="quoi">Ce qui est supprimé, ce qui est conservé</h2>
  <div class="tbl"><table>
    <thead><tr><th>Données</th><th>Après la suppression</th></tr></thead>
    <tbody>
      <tr><td>Compte, profil, photos, vidéos, commentaires</td><td>Supprimés immédiatement</td></tr>
      <tr><td>Messages, candidatures, favoris, abonnements à des matchs</td><td>Supprimés immédiatement</td></tr>
      <tr><td>Pièce d'identité et documents de vérification</td><td>Supprimés immédiatement</td></tr>
      <tr><td>Historique d'activité (vues, j'aime)</td><td>Rendu anonyme : plus aucun lien avec vous</td></tr>
      <tr><td>Copies de sauvegarde</td><td>Effacées sous 30 jours</td></tr>
      <tr><td>Reçus de paiement</td><td>Conservés pendant la durée imposée par la loi comptable</td></tr>
      <tr><td>Signalements liés à la sécurité d'un enfant ou à une fraude</td><td>Conservés tant que la loi ou une enquête l'exige</td></tr>
    </tbody>
  </table></div>
  <div class="callout"><b>Abonnement en cours ?</b> Supprimer le compte arrête le service. Si vous avez payé par Google Play ou l'App Store, annulez aussi l'abonnement dans le store pour éviter un nouveau prélèvement.</div>
  <p>Plus de détails dans la <a href="{{ route('privacy') }}#suppression">politique de confidentialité</a>.</p>
</article>
</div>
@endsection
