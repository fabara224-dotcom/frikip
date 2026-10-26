# Frikip

Plateforme de recrutement sportif pour l'Afrique de l'Ouest. Les sportifs publient leurs compilations vidéo. Les clubs, académies, agents et universités les trouvent et les contactent.

Site web en **Laravel 11** (PHP 8.2 ou plus récent), base **SQLite** par défaut.

## Ce que fait le site

- **Fil vidéo vertical** : les vidéos sont classées par un algorithme réglable dans l'admin (pays du visiteur, sports qu'il regarde, comptes suivis, nouveautés, Boost payant).
- **Profils sportifs**, CV, carte de joueur, vérification d'identité, étoile bleue selon les abonnés.
- **Messagerie** : textes, vocaux, photos, appels audio et vidéo.
- **Recruteurs** : recherche, alertes, offres et détections avec billet QR.
- **Défis** :
  - défi de la semaine, plus des défis spéciaux avec un lot et un sponsor ;
  - votes et classement en direct ;
  - Boost offert au gagnant.
- **Coaching** : un programme gratuit selon le poste et les chiffres de la saison, et un suivi payant par un coach.
- **Matchs** :
  - scores en direct des grands championnats (API-Football) ;
  - matchs locaux diffusés par les clubs (YouTube, Facebook, Mux) ;
  - « Où regarder » vers les diffuseurs officiels ;
  - lecture dans Frikip avec l'API d'un diffuseur partenaire.
- **Paiements Mobile Money** (CinetPay) : Premium, Boost, Structure Pro, montage vidéo, coaching, « Sans pub ».
- **Publicités vidéo** gérées dans l'admin, avec ciblage par pays et par sport.
- **Modération** : mots interdits, signalements, sanctions automatiques, détection de la nudité (Sightengine).
- **16 pays** d'Afrique de l'Ouest, avec la monnaie et l'indicatif de chaque pays. Site en français et en anglais.

## Installation

```bash
git clone https://github.com/TON-COMPTE/frikip.git
cd frikip
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan terrain:cles-push     # clés des notifications sur le téléphone
php artisan serve
```

Ouvre ensuite http://127.0.0.1:8000.

## Espace admin

1. Mets un code secret dans `.env`, par exemple `TERRAIN_ADMIN_PASSWORD=un-code-long`. Il ne sert qu'à l'installation.
2. Ouvre http://127.0.0.1:8000/admin. Au premier lancement, la page **Créer le compte administrateur** s'affiche : ton nom, ton e-mail (ton identifiant), ton mot de passe, puis le code d'installation.
3. Ensuite, tu te connectes sur `/admin` avec ton e-mail et ton mot de passe.

Dans **Admin > Comptes admin**, tu ajoutes d'autres personnes :
- **Administrateur** : tous les droits ;
- **Modérateur** : membres, modération et contenus, sans les réglages, les paiements, les publicités ni les comptes admin.

Toutes les actions sont inscrites dans le **Journal**, avec le nom de la personne qui les a faites.

Mot de passe oublié : `php artisan frikip:admin ton@email.com`.

Ce que l'admin peut faire :
- **Membres** : chercher, modifier le profil, changer le mot de passe, avertir, suspendre, bloquer, débloquer, vérifier le compte, offrir Premium, Boost ou « Sans pub », supprimer le compte.
- **Contenus** : voir et supprimer les vidéos, offres, détections et commentaires ; modération des signalements et des vidéos arrêtées par l'algorithme.
- **Algorithme** : classement, fil, sanctions automatiques, mots interdits.
- **Direct, Diffuseurs, Publicités, Défis, Coaching** : ajouter, modifier, supprimer.
- **Paiements** : historique, validation à la main (espèces, virement), annulation.
- **Montages, Vérifications, Clubs, Témoignages, Partenaires, Suppressions de compte**.

Sur un serveur, ajoute cette tâche cron : `* * * * * php artisan schedule:run`. Elle gère les alertes, les défis, la recherche des matchs en direct et les paiements en attente.

## Clés à remplir dans `.env`

Toutes sont facultatives : sans elles, le site fonctionne, et la fonction correspondante attend sa clé.

| Fonction | Variables |
|---|---|
| Paiements Mobile Money | `CINETPAY_API_KEY`, `CINETPAY_SITE_ID` |
| Scores des championnats | `API_FOOTBALL_KEY` |
| Matchs en direct trouvés sur YouTube | `YOUTUBE_API_KEY` |
| Diffusion depuis un téléphone | `MUX_TOKEN_ID`, `MUX_TOKEN_SECRET` |
| API d'un diffuseur partenaire | `LIVE_PARTNER_*` (voir plus bas) |
| Détection de la nudité | `SIGHTENGINE_USER`, `SIGHTENGINE_SECRET` |
| SMS et WhatsApp | `TWILIO_*` |

## Brancher un diffuseur (matchs en direct)

Tu as un accord avec un diffuseur, ou un fournisseur de flux sous licence, et il te donne une API qui renvoie le lien vidéo d'un match ? Remplis ces variables dans `.env` :

```
LIVE_PARTNER_NAME="Nom du diffuseur"
LIVE_PARTNER_URL="https://api.diffuseur.com/v1/streams?fixture={fixture}"
LIVE_PARTNER_KEY=la-cle-fournie
LIVE_PARTNER_HEADER=Authorization
LIVE_PARTNER_FIELD=data.hls_url
```

- `{fixture}` est remplacé par le numéro du match API-Football. Sont aussi disponibles : `{league}`, `{home}`, `{away}`, `{date}` et `{country}`.
- `LIVE_PARTNER_FIELD` indique où se trouve le lien dans la réponse JSON.
- Le lien peut être un flux HLS `.m3u8`, un fichier MP4 ou une page d'intégration. Il se lit dans le lecteur de la page du match.

Si ton diffuseur fonctionne autrement, tout se passe dans `app/Terrain/Growth/PartnerStreams.php`. Il existe aussi deux réglages qui ne demandent aucun code, dans l'admin :

- **Admin > Diffuseurs** : un lien « Regarder » par compétition et par pays, avec un lien partenaire possible, ou un flux sous contrat ;
- **Admin > Direct** : un flux relié à un match précis.

N'intègre que des flux pour lesquels tu as l'autorisation du détenteur des droits.

## Organisation du code

- `app/Terrain/` : données (comptes, sportifs, vidéos), pays, scores en direct.
- `app/Terrain/Growth/` : algorithme, modération, paiements, défis, coaching, directs, publicités, diffuseurs.
- `app/Http/Controllers/` : pages du site et de l'admin.
- `resources/views/web/` : pages du site ; `resources/views/admin/` : espace admin.
- `lang/en.json` : traduction anglaise.

## Commandes utiles

```bash
php artisan frikip:directs --maintenant      # chercher les matchs en direct sur YouTube
php artisan frikip:nettoyer-exemples         # supprimer les anciennes données d'exemple
php artisan terrain:diagnostic               # vérifier la configuration
```

## Contact

frikip@gmail.com · 00224 625112022 · 00224 626432276

© Fabara – Conakry.
