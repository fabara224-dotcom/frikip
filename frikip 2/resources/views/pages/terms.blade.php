@extends('layouts.app')

@section('title', 'Conditions d\'utilisation – '.config('terrain.app_name'))
@section('description', "Conditions générales d'utilisation de l'application.")

@section('content')
<div class="doc gut">
<article class="narrow">
  <span class="eyebrow">Conditions générales d'utilisation</span>
  <h1>Les règles de <x-cfg key="app_name" /></h1>
  <p class="lead">En créant un compte, vous acceptez ces conditions. Elles expliquent ce que vous pouvez faire dans l'application, ce qui est interdit et ce qui se passe en cas de problème.</p>
  <p class="meta">Dernière mise à jour : <x-cfg key="last_update" /></p>

  <nav class="toc" aria-label="Sommaire"><b>Sommaire</b>
    <ol>
      <li><a href="#service">Le service</a></li>
      <li><a href="#compte">Votre compte</a></li>
      <li><a href="#recruteurs">Règles pour les recruteurs</a></li>
      <li><a href="#contenus">Vos vidéos et contenus</a></li>
      <li><a href="#interdit">Ce qui est interdit</a></li>
      <li><a href="#moderation">Signalement et sanctions</a></li>
      <li><a href="#paiements">Services payants</a></li>
      <li><a href="#scores">Scores et notes</a></li>
      <li><a href="#responsabilite">Responsabilité</a></li>
      <li><a href="#fin">Fin du compte</a></li>
      <li><a href="#loi">Loi applicable</a></li>
    </ol>
  </nav>

  <h2 id="service">1. Le service</h2>
  <p>{{ config('terrain.app_name', 'Frikip') }}, édité par <b><x-cfg key="editor" /></b>, permet aux sportifs de publier un profil et des compilations vidéo, aux recruteurs (clubs, académies, agents, fédérations, universités) de les trouver et de les contacter, et à tous de suivre les scores des compétitions. <x-cfg key="app_name" /> est un outil de mise en relation : nous ne sommes ni agent sportif ni employeur, et nous ne garantissons aucun recrutement.</p>

  <h2 id="compte">2. Votre compte</h2>
  <ul>
    <li>Vous devez avoir au moins <x-cfg key="min_age" /> ans. Entre <x-cfg key="min_age" /> et 17 ans, un parent ou tuteur doit accepter ces conditions avec vous.</li>
    <li>Les informations de votre profil doivent être exactes : votre vraie identité, votre vrai âge, vos vraies performances.</li>
    <li>Un seul compte par personne. Vous êtes responsable de votre mot de passe et de ce qui est fait avec votre compte.</li>
    <li>Vous pouvez supprimer votre compte à tout moment : voir la <a href="{{ route('deletion') }}">page de suppression</a>.</li>
  </ul>

  <h2 id="recruteurs">3. Règles pour les recruteurs</h2>
  <ul>
    <li>Pour écrire aux sportifs ou publier une offre, vous devez faire vérifier votre structure (licence de club, d'agent ou document officiel).</li>
    <li><b>Il est interdit de demander de l'argent à un sportif</b> ou à sa famille : frais d'essai, de dossier, de visa, d'hébergement ou d'agent. Toute demande de ce type entraîne la fermeture immédiate du compte et peut être signalée aux autorités.</li>
    <li>Vous ne contactez un mineur que dans un cadre sportif, par la messagerie de l'application, avec son tuteur informé.</li>
    <li>Les offres publiées doivent être réelles, avec un lieu, une date et des critères exacts.</li>
  </ul>

  <h2 id="contenus">4. Vos vidéos et contenus</h2>
  <p>Vous restez propriétaire de vos vidéos, photos et textes. En les publiant, vous nous autorisez, gratuitement et dans le monde entier, à les héberger, les compresser, les afficher dans l'application et à les montrer aux recruteurs, aussi longtemps qu'ils sont en ligne. Cette autorisation prend fin quand vous les supprimez ou supprimez votre compte, sauf pour les copies déjà partagées par d'autres utilisateurs avec les outils de partage.</p>
  <p>Vous garantissez avoir le droit de publier vos contenus, y compris l'accord des autres personnes clairement reconnaissables, et le droit d'utiliser la musique qu'ils contiennent.</p>

  <h2 id="interdit">5. Ce qui est interdit</h2>
  <ul>
    <li>Tout contenu sexuel, toute sexualisation d'un mineur, toute tentative d'approcher un enfant à des fins sexuelles (tolérance zéro, signalement aux autorités) ;</li>
    <li>le harcèlement, les menaces, les insultes, les propos haineux ou discriminatoires ;</li>
    <li>la violence gratuite, sauf les actions de jeu dans un cadre sportif ;</li>
    <li>les faux profils, l'usurpation d'identité, les fausses performances ou les vidéos d'une autre personne présentées comme les vôtres ;</li>
    <li>les arnaques, les demandes d'argent, la publicité non autorisée ;</li>
    <li>la collecte automatique des données des utilisateurs, le piratage ou toute tentative de contourner nos protections.</li>
  </ul>

  <h2 id="moderation">6. Signalement et sanctions</h2>
  <p>Chaque profil, vidéo, commentaire et message peut être signalé depuis l'application. Vous pouvez aussi bloquer un utilisateur. Notre équipe examine les signalements et peut retirer un contenu, limiter ou fermer un compte, sans préavis en cas de danger pour un utilisateur. Vous pouvez contester une décision en écrivant à <x-cfg key="email" mailto />.</p>

  <h2 id="paiements">7. Services payants</h2>
  <p>Certaines options sont payantes, par exemple l'abonnement Recruteur Premium ou la mise en avant d'un profil. Le prix, la durée et le contenu sont affichés avant l'achat. Le paiement se fait par Mobile Money, carte bancaire ou par le système d'achat de Google Play ou de l'App Store. Un abonnement se renouvelle seulement si c'est indiqué au moment de l'achat ; vous pouvez l'annuler à tout moment, il reste alors actif jusqu'à la fin de la période payée. Sauf obligation légale, une période commencée n'est pas remboursée.</p>

  <h2 id="scores">8. Scores et notes</h2>
  <p>Les scores sont saisis par les organisateurs et les clubs ; les notes des joueurs viennent des organisateurs, de scouts vérifiés et du vote du public. Nous faisons notre possible pour qu'ils soient exacts, mais ils peuvent contenir des erreurs. Signalez-les-nous pour correction.</p>

  <h2 id="responsabilite">9. Responsabilité</h2>
  <p>Chaque utilisateur est responsable de ce qu'il publie et de ses échanges. Nous ne sommes pas partie aux accords conclus entre sportifs et recruteurs. Nous nous efforçons de garder l'application disponible et sûre, sans pouvoir garantir une disponibilité permanente. Rien dans ces conditions ne limite les droits que la loi vous accorde.</p>

  <h2 id="fin">10. Fin du compte</h2>
  <p>Vous pouvez supprimer votre compte à tout moment. Nous pouvons fermer un compte qui ne respecte pas ces conditions. Ce qui arrive à vos données est expliqué dans la <a href="{{ route('privacy') }}#suppression">politique de confidentialité</a>.</p>

  <h2 id="loi">11. Loi applicable et contact</h2>
  <p>Ces conditions sont soumises au droit de la République de <x-cfg key="country" />, sans vous priver des protections de la loi de votre pays de résidence. En cas de désaccord, écrivez-nous d'abord : <x-cfg key="email" mailto />. Nous pouvons modifier ces conditions ; nous vous prévenons dans l'application avant tout changement important.</p>
  <p><b><x-cfg key="editor" /></b> · <x-cfg key="editor_address" /></p>
</article>
</div>
@endsection
