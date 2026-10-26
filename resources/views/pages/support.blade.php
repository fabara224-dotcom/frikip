@extends('layouts.app')

@section('title', 'Aide et contact – '.config('terrain.app_name'))
@section('description', "Aide, questions fréquentes et contact de l'équipe.")

@section('content')
<div class="doc gut">
<article class="narrow">
  <span class="eyebrow">Aide et contact</span>
  <h1>Comment pouvons-nous vous aider ?</h1>
  <p class="lead">L'équipe <x-cfg key="app_name" /> répond en français, en général sous 48 heures.</p>

  <div class="card">
    <b>Écrire au support</b>
    <div class="copy"><span id="sup-mail"><x-cfg key="email" /></span><button class="btn ghost" type="button" data-copy="#sup-mail">Copier</button></div>
    <p class="meta" style="margin:0">Indiquez l'e-mail ou le numéro de votre compte et, si possible, une capture d'écran.</p>
  </div>

  <div class="card">
    <b>Appeler ou écrire sur WhatsApp</b>
    <p style="margin:0"><x-phones wa sep="<br>" /></p>
  </div>

  <h2 id="faq">Questions fréquentes</h2>
  <h3>Comment faire vérifier mon profil ?</h3>
  <p>Dans l'application : Profil &gt; Vérification. Envoyez une photo de votre pièce d'identité. Le badge apparaît sous 24 à 48 heures. La pièce n'est jamais publiée et elle est supprimée 30 jours après la vérification.</p>
  <h3>Je suis un club ou un agent : comment écrire aux sportifs ?</h3>
  <p>Créez un compte recruteur et envoyez votre licence de club ou d'agent, ou un document officiel de votre structure. Après vérification, vous pourrez écrire aux sportifs et publier des offres.</p>
  <h3>Un recruteur me demande de l'argent pour un essai. Que faire ?</h3>
  <p>Ne payez pas. Un vrai club ne demande jamais d'argent pour un essai, un visa ou un transfert. Touchez « Signaler » dans la conversation : nous fermons ces comptes. Voir nos <a href="{{ route('child-safety') }}">normes de sécurité</a>.</p>
  <h3>Comment corriger un score ou une note de match ?</h3>
  <p>Écrivez-nous avec le nom du match et la correction. Nous la vérifions avec l'organisateur.</p>
  <h3>Comment annuler mon abonnement Premium ?</h3>
  <p>Si vous avez payé par Mobile Money, l'abonnement ne se renouvelle pas tout seul. Si vous avez payé par Google Play ou l'App Store, annulez-le dans les abonnements de votre store.</p>
  <h3>Comment supprimer mon compte ?</h3>
  <p>Depuis l'application (Profil &gt; Paramètres et confidentialité &gt; Supprimer mon compte) ou sur la <a href="{{ route('deletion') }}">page de suppression</a>.</p>

  <h2 id="signaler">Signaler un contenu ou un comportement</h2>
  <p>Le plus rapide est le bouton « Signaler » dans l'application, sur le profil, la vidéo ou la conversation. Pour un danger concernant un enfant, écrivez aussi à <x-cfg key="safety_email" mailto />.</p>

  <h2 id="editeur">Éditeur</h2>
  <p><b><x-cfg key="editor" /></b><br><x-cfg key="editor_address" /><br><x-cfg key="country" /></p>
</article>
</div>
@endsection
