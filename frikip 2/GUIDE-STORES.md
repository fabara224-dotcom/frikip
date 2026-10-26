# Remplir Google Play et l'App Store

L'installation et la mise en ligne du site sont expliquées dans README-LARAVEL.md.

## 1. Les adresses à donner aux stores

Remplace `https://TON-SITE` par ton adresse.

| Champ demandé | Adresse |
| --- | --- |
| Politique de confidentialité (Google et Apple) | `https://TON-SITE/confidentialite` |
| Suppression de compte (Google Play, Sécurité des données) | `https://TON-SITE/suppression-compte` |
| Normes de sécurité des enfants (Google Play, apps sociales) | `https://TON-SITE/securite-enfants` |
| URL d'assistance (Apple, obligatoire) | `https://TON-SITE/support` |
| URL marketing (Apple, facultatif) | `https://TON-SITE/` |
| Conditions d'utilisation (EULA, Apple) | `https://TON-SITE/conditions` |

## 2. Google Play : formulaire « Sécurité des données »

Play Console > Règles et programmes > Contenu de l'appli > Sécurité des données.

- Votre appli collecte-t-elle ou partage-t-elle des données ? **Oui**
- Toutes les données sont-elles chiffrées en transit ? **Oui**
- Les utilisateurs peuvent-ils demander la suppression de leurs données ? **Oui**, avec le lien de suppression ci-dessus
- Créer un compte : **Oui, avec un nom d'utilisateur et un mot de passe**

| Type de données Google | Collectée | Partagée | Facultative | Utilisation |
| --- | --- | --- | --- | --- |
| Nom | Oui | Non | Non | Fonctionnalités, gestion du compte |
| Adresse e-mail | Oui | Non | Non | Gestion du compte, communications |
| Numéro de téléphone | Oui | Non | Oui | Gestion du compte, sécurité (tuteur) |
| Identifiants utilisateur | Oui | Non | Non | Gestion du compte |
| Autres infos (âge, sport, taille…) | Oui | Non | Non | Fonctionnalités |
| Photos | Oui | Non | Oui | Fonctionnalités, sécurité (pièce d'identité) |
| Vidéos | Oui | Non | Oui | Fonctionnalités |
| Autres messages dans l'appli | Oui | Non | Oui | Fonctionnalités, sécurité |
| Historique d'achats | Oui | Non | Oui | Fonctionnalités |
| Interactions avec l'appli | Oui | Non | Non | Analyse, personnalisation |
| Historique de recherche dans l'appli | Oui | Non | Non | Personnalisation |
| Journaux de plantage / diagnostics | Oui | Non | Non | Analyse |
| Identifiants d'appareil | Oui | Non | Non | Notifications |

« Partagée » = **Non**, car les prestataires (Supabase, paiement, notifications) agissent pour ton compte : Google ne les compte pas comme un partage.

Autres déclarations Play Console :
- **Public cible** : 13 ans et plus. Ne coche pas les tranches de moins de 13 ans.
- **Catégorie** : Sports. Si tu déclares l'app comme « Réseaux sociaux », la page sécurité des enfants est obligatoire. Garde-la de toute façon.
- **Normes de sécurité des enfants** : donne le lien, désigne la personne de contact, certifie le signalement dans l'app (Paramètres et confidentialité > Signaler un problème, et les boutons Signaler).
- **Questionnaire de classification** : contenu généré par les utilisateurs **Oui**, interaction entre utilisateurs **Oui**, achats intégrés **Oui**.

## 3. Apple : « Confidentialité de l'app »

App Store Connect > ton app > Confidentialité de l'app.

- Données collectées : Coordonnées (nom, e-mail, téléphone), Contenu utilisateur (photos ou vidéos, autre contenu utilisateur, messages), Identifiants (ID utilisateur, ID de l'appareil), Achats (historique d'achats), Utilisation (interactions avec le produit), Diagnostics (données de plantage).
- Liées à l'identité de l'utilisateur : **Oui** (sauf les diagnostics).
- Utilisées pour le suivi publicitaire (tracking) : **Non**. Pas besoin de la fenêtre « App Tracking Transparency ».
- Âge : réponds au questionnaire en indiquant les messages entre utilisateurs et le contenu généré par les utilisateurs.

Apple vérifie aussi que :
- le compte se supprime **depuis l'app** (fait : Paramètres et confidentialité > Supprimer mon compte) ;
- on peut **signaler** un contenu et **bloquer** un utilisateur ;
- un **compte de démonstration** est fourni dans « Informations pour la vérification de l'app » : crée un compte sportif et un compte recruteur vérifié de test, et donne leurs identifiants.

## 4. Ce que les textes promettent et qu'il faut faire avant la publication

Les pages légales décrivent le fonctionnement prévu. Vérifie que l'app le fait vraiment avant d'envoyer aux stores, sinon adapte le texte :

- [ ] SMS de confirmation au tuteur avant d'activer un compte de moins de 18 ans
- [ ] Copie au tuteur des messages reçus par un mineur
- [ ] Seuls les recruteurs vérifiés peuvent écrire (déjà dans les règles de la base)
- [ ] Bouton « Bloquer un utilisateur » (à ajouter dans le profil et la conversation)
- [ ] Analyse automatique des images et vidéos interdites (ex. service de modération)
- [ ] Suppression des pièces d'identité 30 jours après la vérification
- [ ] Suppression des comptes inactifs depuis 3 ans
- [ ] Anonymisation mensuelle du journal d'activité (`anonymize_old_events`, à planifier avec pg_cron)

Ces textes sont un modèle de départ sérieux, pas un avis juridique. Fais-les relire par un juriste en Guinée avant le lancement public, surtout si tu ouvres l'app dans d'autres pays.
