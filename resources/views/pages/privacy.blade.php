@extends('layouts.app')

@section('title', 'Confidentialité – '.config('terrain.app_name'))
@section('description', "Politique de confidentialité de l'application : données collectées, utilisation, partage, conservation, droits et suppression du compte.")

@section('content')
<div class="doc gut">
<article class="narrow">
  <span class="eyebrow">Politique de confidentialité</span>
  <h1>Vos données dans <x-cfg key="app_name" /></h1>
  <p class="lead">Cette politique explique quelles données l'application <x-cfg key="app_name" /> collecte, pourquoi, avec qui elles sont partagées, combien de temps elles sont gardées et comment vous pouvez les consulter ou les supprimer.</p>
  <p class="meta">Dernière mise à jour : <x-cfg key="last_update" /></p>

  <div class="callout key"><b>En bref.</b> Nous collectons les informations de votre profil sportif, vos vidéos et votre activité dans l'application pour vous mettre en relation avec des recruteurs. Nous ne vendons pas vos données. Nous ne montrons pas de publicité ciblée aux mineurs. Vous pouvez <a href="#suppression">supprimer votre compte</a> à tout moment, depuis l'application ou depuis <a href="{{ route('deletion') }}">cette page</a>.</div>

  <nav class="toc" aria-label="Sommaire"><b>Sommaire</b>
    <ol>
      <li><a href="#responsable">Qui est responsable</a></li>
      <li><a href="#donnees">Données collectées</a></li>
      <li><a href="#finalites">Pourquoi nous les utilisons</a></li>
      <li><a href="#recommandations">Recommandations et IA</a></li>
      <li><a href="#visibilite">Qui voit vos données</a></li>
      <li><a href="#prestataires">Prestataires</a></li>
      <li><a href="#transferts">Transferts hors de votre pays</a></li>
      <li><a href="#conservation">Durées de conservation</a></li>
      <li><a href="#mineurs">Mineurs</a></li>
      <li><a href="#securite">Sécurité</a></li>
      <li><a href="#droits">Vos droits</a></li>
      <li><a href="#suppression">Supprimer votre compte</a></li>
      <li><a href="#site">Ce site web</a></li>
      <li><a href="#modifications">Modifications</a></li>
      <li><a href="#contact">Nous contacter</a></li>
    </ol>
  </nav>

  <h2 id="responsable">1. Qui est responsable de vos données</h2>
  <p>L'application <x-cfg key="app_name" /> est éditée par <b><x-cfg key="editor" /></b>, <x-cfg key="editor_address" />, <x-cfg key="country" />. Nous sommes responsables du traitement de vos données personnelles.</p>
  <p>Pour toute question sur vos données : <x-cfg key="privacy_email" mailto />.</p>

  <h2 id="donnees">2. Les données que nous collectons</h2>
  <div class="tbl"><table>
    <thead><tr><th>Catégorie</th><th>Exemples</th><th>Obligatoire ?</th></tr></thead>
    <tbody>
      <tr><td>Compte</td><td>Adresse e-mail ou numéro de téléphone, mot de passe (chiffré), rôle (sportif ou recruteur)</td><td>Oui</td></tr>
      <tr><td>Profil sportif</td><td>Nom, âge, pays, ville, taille, poids, sport, poste, club, niveau, disponibilité, statistiques et palmarès</td><td>Nom, âge, pays et sport : oui. Le reste : facultatif</td></tr>
      <tr><td>Photos et vidéos</td><td>Compilations, vidéo de présentation, titres, compétitions, moments clés</td><td>Non</td></tr>
      <tr><td>Vérification d'identité</td><td>Photo d'une pièce d'identité ; pour les recruteurs, licence de club ou d'agent</td><td>Non, mais nécessaire pour le badge vérifié et pour écrire aux sportifs (recruteurs)</td></tr>
      <tr><td>Parent ou tuteur</td><td>Numéro de téléphone du parent ou tuteur d'un sportif de moins de 18 ans</td><td>Oui pour les moins de 18 ans</td></tr>
      <tr><td>Messages</td><td>Contenu des conversations entre sportifs et recruteurs, signalements</td><td>Non</td></tr>
      <tr><td>Activité dans l'app</td><td>Vidéos regardées et temps de visionnage, j'aime, commentaires, partages, profils consultés, recherches, candidatures, votes</td><td>Collectée automatiquement</td></tr>
      <tr><td>Matchs et scores</td><td>Pour les organisateurs : matchs créés, scores et événements saisis</td><td>Non</td></tr>
      <tr><td>Achats</td><td>Offre achetée (Premium, boost), montant, date, référence de transaction. Nous ne recevons jamais votre code Mobile Money ni votre numéro de carte complet</td><td>Seulement si vous achetez</td></tr>
      <tr><td>Appareil et technique</td><td>Modèle de téléphone, version du système, langue, identifiant de notification, journaux d'erreurs, adresse IP</td><td>Collectée automatiquement</td></tr>
    </tbody>
  </table></div>
  <p>Nous ne collectons pas votre position GPS précise, vos contacts ni vos données de santé. Nous accédons à votre galerie, à votre appareil photo et à votre micro uniquement quand vous choisissez de publier une vidéo, et seulement avec votre autorisation.</p>

  <h2 id="finalites">3. Pourquoi nous utilisons vos données</h2>
  <div class="tbl"><table>
    <thead><tr><th>Utilisation</th><th>Données concernées</th><th>Base</th></tr></thead>
    <tbody>
      <tr><td>Créer et gérer votre compte, vous connecter</td><td>Compte, appareil</td><td>Exécution du service</td></tr>
      <tr><td>Afficher votre profil et vos vidéos aux recruteurs et aux autres utilisateurs</td><td>Profil, vidéos</td><td>Exécution du service</td></tr>
      <tr><td>Mettre en relation sportifs et recruteurs, gérer offres et candidatures</td><td>Profil, vidéos, messages</td><td>Exécution du service</td></tr>
      <tr><td>Vérifier l'identité et l'âge, protéger les mineurs, lutter contre les faux agents et les arnaques</td><td>Vérification, tuteur, messages signalés</td><td>Obligation légale et intérêt légitime (sécurité)</td></tr>
      <tr><td>Personnaliser le fil et les recherches</td><td>Activité, profil</td><td>Intérêt légitime ; vous pouvez vous y opposer (voir section 4)</td></tr>
      <tr><td>Afficher les scores, classements et notes des joueurs</td><td>Matchs, profil</td><td>Exécution du service</td></tr>
      <tr><td>Traiter les paiements et éditer les reçus</td><td>Achats</td><td>Exécution du contrat et obligations comptables</td></tr>
      <tr><td>Envoyer des notifications (buts, messages, offres)</td><td>Appareil, activité</td><td>Votre consentement, modifiable dans les réglages du téléphone</td></tr>
      <tr><td>Corriger les bugs et améliorer l'application</td><td>Technique, activité (statistiques globales)</td><td>Intérêt légitime</td></tr>
    </tbody>
  </table></div>
  <p>Nous ne vendons pas vos données et ne les louons à personne.</p>

  <h2 id="recommandations">4. Recommandations et intelligence artificielle</h2>
  <p>Pour vous montrer les vidéos et les profils les plus pertinents, <x-cfg key="app_name" /> utilise votre activité : les vidéos que vous regardez jusqu'au bout, celles que vous aimez, les profils que vous consultez. Les recruteurs se voient proposer des sportifs qui correspondent à leurs recherches et à leurs offres.</p>
  <p>Ces recommandations ne produisent aucune décision qui vous concerne légalement : la décision de recruter appartient toujours à un recruteur humain. Nous pouvons analyser automatiquement les vidéos publiées pour repérer les actions (buts, arrêts, sprints) et pour détecter les contenus interdits.</p>
  <p>Vous pouvez demander la suppression de votre historique d'activité à tout moment en écrivant à <x-cfg key="privacy_email" mailto />. Les données d'activité utilisées pour entraîner nos modèles sont dissociées de votre identité (pseudonymisées).</p>

  <h2 id="visibilite">5. Qui voit vos données</h2>
  <ul>
    <li><b>Tout le monde dans l'app</b> : votre profil sportif (sans votre e-mail, votre téléphone ni votre pièce d'identité), vos vidéos publiées, vos commentaires.</li>
    <li><b>Profils des moins de 18 ans</b> : seuls les recruteurs vérifiés peuvent leur écrire. Les messages reçus sont transmis en copie au parent ou tuteur.</li>
    <li><b>Messages</b> : seuls l'expéditeur et le destinataire, et notre équipe de modération en cas de signalement.</li>
    <li><b>Pièce d'identité et licence</b> : uniquement notre équipe de vérification. Jamais publiées.</li>
    <li><b>Autorités</b> : si la loi l'exige ou pour protéger une personne en danger, en particulier un enfant.</li>
  </ul>

  <h2 id="prestataires">6. Nos prestataires</h2>
  <p>Nous faisons appel à des entreprises qui traitent des données pour notre compte, selon nos instructions, et qui n'ont pas le droit de les utiliser pour elles-mêmes :</p>
  <div class="tbl"><table>
    <thead><tr><th>Prestataire</th><th>Rôle</th></tr></thead>
    <tbody>
      <tr><td>Supabase</td><td>Hébergement de la base de données, des comptes et des fichiers</td></tr>
      <tr><td>Service de diffusion vidéo</td><td>Stockage, compression et lecture des vidéos</td></tr>
      <tr><td>Google Firebase Cloud Messaging et Apple Push Notification</td><td>Envoi des notifications</td></tr>
      <tr><td>Passerelle de paiement mobile (Orange Money, MTN MoMo, cartes)</td><td>Traitement des paiements</td></tr>
      <tr><td>Opérateur SMS</td><td>Codes de connexion et confirmation du tuteur</td></tr>
    </tbody>
  </table></div>
  <p>La liste à jour des prestataires est disponible sur simple demande.</p>

  <h2 id="transferts">7. Transferts hors de votre pays</h2>
  <p>Certains prestataires hébergent les données hors de <x-cfg key="country" />, notamment en Europe. Nous choisissons des prestataires qui offrent des garanties de sécurité et de confidentialité, et nous encadrons ces transferts par des engagements contractuels.</p>

  <h2 id="conservation">8. Combien de temps nous gardons vos données</h2>
  <div class="tbl"><table>
    <thead><tr><th>Données</th><th>Durée</th></tr></thead>
    <tbody>
      <tr><td>Compte, profil, vidéos, messages</td><td>Tant que votre compte existe. Supprimés dès la suppression du compte ; effacés des sauvegardes sous 30 jours</td></tr>
      <tr><td>Pièce d'identité</td><td>Supprimée 30 jours après la vérification</td></tr>
      <tr><td>Historique d'activité</td><td>24 mois, puis rendu anonyme. Rendu anonyme dès la suppression du compte</td></tr>
      <tr><td>Reçus de paiement</td><td>Durée imposée par la loi comptable, même après la suppression du compte</td></tr>
      <tr><td>Signalements et preuves liés à la sécurité des enfants ou à une fraude</td><td>Le temps nécessaire à l'enquête et aux obligations légales, même après la suppression du compte</td></tr>
      <tr><td>Compte inactif</td><td>Après 3 ans sans connexion, nous vous prévenons puis supprimons le compte</td></tr>
    </tbody>
  </table></div>

  <h2 id="mineurs">9. Mineurs</h2>
  <p>Il faut avoir au moins <x-cfg key="min_age" /> ans pour créer un compte. Entre <x-cfg key="min_age" /> et 17 ans, un parent ou tuteur doit donner son accord ; il est contacté par SMS avant l'activation du compte et peut demander à tout moment la suppression du compte de l'enfant.</p>
  <p>Pour les moins de 18 ans : aucune publicité ciblée, messages réservés aux recruteurs vérifiés, copie des messages au tuteur, données jamais utilisées à des fins commerciales en dehors du service. Si nous apprenons qu'un enfant de moins de <x-cfg key="min_age" /> ans a créé un compte, nous le supprimons. Nos règles de protection sont détaillées dans nos <a href="{{ route('child-safety') }}">normes de sécurité des enfants</a>.</p>

  <h2 id="securite">10. Sécurité</h2>
  <p>Les échanges sont chiffrés (HTTPS). Les mots de passe sont chiffrés et nous ne pouvons pas les lire. L'accès aux données est limité par des règles techniques : chaque utilisateur ne peut modifier que ses propres données, et seule une équipe restreinte accède aux pièces d'identité. Aucun système n'est parfaitement sûr ; en cas de violation de données qui présente un risque pour vous, nous vous prévenons et informons l'autorité compétente lorsque la loi l'exige.</p>

  <h2 id="droits">11. Vos droits</h2>
  <p>Conformément à la loi L/2016/037/AN relative à la cybersécurité et à la protection des données à caractère personnel en République de Guinée, et aux lois des autres pays où l'application est disponible, vous pouvez :</p>
  <ul>
    <li><b>accéder</b> à vos données et en obtenir une copie ;</li>
    <li><b>corriger</b> vos données, directement dans votre profil ;</li>
    <li><b>supprimer</b> votre compte et vos données ;</li>
    <li><b>vous opposer</b> à la personnalisation de votre fil ou <b>retirer votre consentement</b> aux notifications ;</li>
    <li><b>récupérer</b> vos vidéos et données dans un format lisible.</li>
  </ul>
  <p>Écrivez à <x-cfg key="privacy_email" mailto />. Nous répondons dans un délai d'un mois. Nous pouvons vous demander de prouver votre identité. Vous pouvez aussi saisir l'autorité de protection des données de votre pays.</p>

  <h2 id="suppression">12. Supprimer votre compte</h2>
  <div class="callout key">
    <p style="margin-top:0"><b>Depuis l'application :</b> Profil (ou Compte) &gt; Paramètres et confidentialité &gt; <b>Supprimer mon compte</b>.</p>
    <p style="margin-bottom:0"><b>Depuis le web, sans l'application :</b> <a href="{{ route('deletion') }}">page de suppression de compte</a>, ou par e-mail à <x-cfg key="privacy_email" mailto />.</p>
  </div>
  <p>La suppression efface votre compte, votre profil, vos vidéos, vos commentaires, vos messages et vos candidatures. Votre historique d'activité est rendu anonyme. Seules les données de la section 8 que la loi nous oblige à garder sont conservées. Si vous avez un abonnement, annulez-le aussi auprès de votre moyen de paiement pour éviter un nouveau prélèvement.</p>

  <h2 id="site">13. Ce site web</h2>
  <p>Ce site n'utilise ni cookie publicitaire ni outil de suivi. Si vous utilisez le formulaire de suppression de compte, nous enregistrons votre e-mail et votre demande uniquement pour la traiter.</p>

  <h2 id="modifications">14. Modifications</h2>
  <p>Nous pouvons modifier cette politique. En cas de changement important, nous vous prévenons dans l'application avant qu'il ne s'applique. La date de mise à jour figure en haut de cette page.</p>

  <h2 id="contact">15. Nous contacter</h2>
  <p><b><x-cfg key="editor" /></b> · <x-cfg key="editor_address" /><br>Confidentialité : <x-cfg key="privacy_email" mailto /><br>Support : <x-cfg key="email" mailto /></p>
</article>
</div>
@endsection
