# Mettre FootProWorld sur Laravel Cloud

Le code est prêt pour Laravel Cloud : base PostgreSQL (ou MySQL) et vidéos/photos dans un bucket.
Sur Laravel Cloud le disque du serveur est effacé à chaque déploiement : **sans bucket, les vidéos se perdent**.

## 1. Créer l'application
1. cloud.laravel.com → **New application** → dépôt GitHub `fabara224-dotcom/frikip`, branche `main`.
2. Région : la plus proche de la Guinée (Europe, par ex. Frankfurt ou London).

## 2. Base de données
Environnement → **Add database** → *Laravel Serverless Postgres* (ou MySQL) → l'attacher à l'environnement.
Les variables `DB_*` sont remplies automatiquement.

## 3. Stockage des vidéos (bucket)
Environnement → **Add bucket** (Object storage) → visibilité **Public** → nom du disque : `s3` → **ne pas** le mettre comme disque par défaut.
Les variables `AWS_*` sont remplies automatiquement. Ajouter ensuite la variable `MEDIA_DISK=s3`.
(Bucket privé : ajouter aussi `MEDIA_SIGNED_URLS=true`.)

## 4. Variables d'environnement
Reprendre celles de l'ancien serveur — **surtout le même `APP_KEY`** — puis :
```
APP_NAME=FootProWorld
TERRAIN_APP_NAME=FootProWorld
APP_ENV=production
APP_DEBUG=false
APP_URL=https://footproworld.com
MEDIA_DISK=s3
FRIKIP_NOUVEAU_DESIGN=true
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
```
+ les clés existantes (CinetPay, API-Football, YouTube, Google/Facebook/TikTok, Cloudflare, e-mail…).

## 5. Déploiement
- Build commands : laisser `composer install --no-dev --optimize-autoloader`
- Deploy commands : `php artisan migrate --force`
- Activer le **Scheduler** (tâches automatiques).
- Garder **1 seule réplique** au début (les envois de vidéos en plusieurs morceaux passent par le même serveur).

## 6. Récupérer les données de l'ancien serveur (une seule fois)
Sur un ordinateur qui a le projet, avec l'ancienne base `database.sqlite` et le dossier `public/uploads` :
1. Mettre dans le `.env` de cet ordinateur les accès de la base Laravel Cloud (onglet de la base → *connection details*) et du bucket (`AWS_*`, `MEDIA_DISK=s3`).
2. Lancer :
```
php artisan migrate --force
php artisan frikip:importer-sqlite chemin/vers/database.sqlite
php artisan frikip:fichiers-vers-bucket --prives
```

## 7. Nom de domaine (Namecheap)
Laravel Cloud → environnement → **Domains** → ajouter `footproworld.com` (et `www.footproworld.com`).
Laravel Cloud affiche les enregistrements DNS à créer → les recopier dans Namecheap → *Advanced DNS*.
Le certificat HTTPS est automatique.

## 8. Limites à connaître
- `ffmpeg` n'est pas installé sur Laravel Cloud : pas d'image d'aperçu automatique ni d'éditeur vidéo côté serveur (les vidéos se lisent quand même).
- Lives des membres : passent par Cloudflare Stream (variables `CLOUDFLARE_*`), pas par le serveur.
