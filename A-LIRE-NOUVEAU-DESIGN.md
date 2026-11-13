# FootProWorld — nouveau design « TikTok du foot »

Ce que contient cette version :
- **/app** : l'application (Accueil, Reels plein écran, Explorer, Publier, Matchs & Lives, Messages, Profil, Menu) branchée sur ta vraie base via l'API `/api/v1`.
- **Lives** : onglet Lives (lives des membres, matchs diffusés, grands matchs, bientôt en direct), lives en tête des stories, boutons « Lancer un live » / « Diffuser un match ».
- **Salons** : chat en direct de chaque match/live avec réactions (⚽ 🔥 👏 🟨 🟥…).
- **Découvrir** : vidéos YouTube mêlées au fil et dans Explorer.
- Appels audio/vidéo depuis Messages, signaler/bloquer, vidéos enregistrées, menu complet (défis, coaching, détections, offres, outils recruteur, Premium, paiements, paramètres…).
- Toutes les anciennes pages du site prennent le même design (noir, vert vif, blanc) et la même barre du bas.

## Installation (après avoir remplacé les fichiers)
```
composer install
php artisan migrate        # ajoute accounts.cover_url
php artisan config:clear
php artisan view:clear
```
Aucune clé ni `.env` n'est inclus : garde ton `.env` actuel.

## Réglages
- `FRIKIP_NOUVEAU_DESIGN=true` (par défaut) : `/fil` ouvre la nouvelle application `/app`.
- Mettre `false` dans `.env` pour revenir à l'ancien fil. L'ancien fil reste visible avec `/fil?ancien=1`.

## Fichiers ajoutés / modifiés
Ajoutés : `app/Http/Controllers/ApiController.php`, `AppShellController.php`, `app/Terrain/Growth/Posters.php`,
`resources/views/web/app-v2.blade.php`, `public/frikip-app/` (CSS + JS de l'application), `public/css/frikip-v5.css`,
`database/migrations/2026_11_07_000000_add_cover_to_accounts.php`.
Modifiés : `routes/web.php`, `resources/views/layouts/web.blade.php`, `config/terrain.php`,
`ExploreController.php`, `FeedController.php`, `PublishController.php`.
