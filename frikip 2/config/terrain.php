<?php

/*
 | Réglages du site Terrain.
 | Les valeurs se remplissent dans le fichier .env (voir README-LARAVEL.md).
 | Une valeur vide s'affiche sur le site en jaune « À compléter » :
 | Google et Apple refuseront l'app tant qu'il en reste.
 */

return [
    'app_name' => env('TERRAIN_APP_NAME', 'Frikip'),
    'editor' => env('TERRAIN_EDITOR', 'Fabara'),
    'editor_address' => env('TERRAIN_EDITOR_ADDRESS', ''),
    'country' => env('TERRAIN_COUNTRY', 'Guinée'),
    'email' => env('TERRAIN_EMAIL') ?: 'frikip@gmail.com',
    // Numéros de contact affichés sur le site (aide, pied de page, WhatsApp)
    'phones' => array_values(array_filter(array_map('trim', explode(',', (string) (env('TERRAIN_TELEPHONES') ?: '00224 625112022,00224 626432276'))))),
    'privacy_email' => env('TERRAIN_PRIVACY_EMAIL') ?: 'frikip@gmail.com',
    'safety_contact_name' => env('TERRAIN_SAFETY_CONTACT_NAME', ''),
    'safety_email' => env('TERRAIN_SAFETY_EMAIL') ?: 'frikip@gmail.com',
    'last_update' => env('TERRAIN_LAST_UPDATE', '20 octobre 2026'),
    'min_age' => (int) env('TERRAIN_MIN_AGE', 13),

    // Contacts gratuits pour un recruteur non Premium
    'free_contacts' => (int) env('TERRAIN_FREE_CONTACTS', 3),

    // Supabase : mêmes valeurs que lib/config.dart dans l'app Flutter.
    // Sans elles, la suppression avec identifiants est désactivée (le formulaire de demande reste disponible).
    'supabase_url' => rtrim((string) env('SUPABASE_URL', ''), '/'),
    'supabase_anon_key' => env('SUPABASE_ANON_KEY', ''),

    // Vraies vidéos de sport libres de droits pour les profils de démo. Commande : php artisan terrain:videos-reelles
    // Pixabay (clé gratuite affichée sur https://pixabay.com/api/docs/ une fois connecté) ou Pexels.
    // Appels audio/vidéo (WebRTC). STUN suffit souvent en Wi-Fi ; sur les réseaux mobiles (4G), un serveur TURN est
    // fortement conseillé, sinon certains appels ne se connectent pas. Ex. : TURN_URLS=turn:turn.exemple.com:3478,turns:turn.exemple.com:5349
    'ice_servers' => array_values(array_filter([
        ['urls' => array_values(array_filter(explode(',', (string) env('STUN_URLS', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302'))))],
        env('TURN_URLS') ? ['urls' => explode(',', (string) env('TURN_URLS')), 'username' => (string) env('TURN_USERNAME', ''), 'credential' => (string) env('TURN_CREDENTIAL', '')] : null,
    ])),

    'pixabay_key' => env('PIXABAY_API_KEY', ''),
    'pexels_key' => env('PEXELS_API_KEY', ''),

    // ---- Paiement Mobile Money (Orange Money, MTN MoMo, carte) via CinetPay : https://cinetpay.com
    // Sans clés : paiement SIMULÉ (pratique pour la démo). Montants en GNF, multiples de 5.
    'cinetpay' => [
        'api_key' => env('CINETPAY_API_KEY', ''),
        'site_id' => env('CINETPAY_SITE_ID', ''),
        // Monnaies ouvertes sur ton compte marchand (à confirmer avec CinetPay) : GNF = Guinée, XOF = pays de l'UEMOA
        'currencies' => env('CINETPAY_CURRENCIES', 'GNF,XOF'),
    ],
    'currency' => env('TERRAIN_CURRENCY', 'GNF'),
    // Offres : [libellé, prix, durée en jours, précision]
    'plans' => [
        'premium' => [['Mensuel', 150000, 30, 'par mois'], ['Annuel', 1500000, 365, '2 mois offerts']],
        'boost' => [['7 jours', 25000, 7, 'en tête du fil'], ['30 jours', 80000, 30, 'fil + recherche']],
    ],
    // Accès Premium gratuit pour les clubs / agents VÉRIFIÉS jusqu'à cette date (AAAA-MM-JJ), vide = désactivé
    'free_club_access_until' => env('TERRAIN_FREE_CLUB_ACCESS_UNTIL', ''),

    // ---- SMS et WhatsApp (codes de connexion, notifications) via Twilio : https://www.twilio.com
    'twilio' => [
        'sid' => env('TWILIO_SID', ''),
        'token' => env('TWILIO_TOKEN', ''),
        'sms_from' => env('TWILIO_SMS_FROM', ''),          // numéro ou nom d'expéditeur
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM', ''), // numéro WhatsApp Business validé
    ],
    'phone_prefix' => env('TERRAIN_PHONE_PREFIX', '224'),
    // Pays affiché aux visiteurs dont on ne connaît pas le pays
    'country_default' => env('TERRAIN_PAYS_DEFAUT', 'Guinée'),
    // Prix dans les autres monnaies (même structure que « plans » et « offers ») ; GNF = prix ci-dessus.
    // Prix XOF indicatifs (1 XOF ≈ 15 GNF), à ajuster après les premières ventes.
    'prices' => [
        'XOF' => [
            'premium' => [['Mensuel', 10000, 30, 'par mois'], ['Annuel', 100000, 365, '2 mois offerts']],
            'boost' => [['7 jours', 2000, 7, 'en tête du fil'], ['30 jours', 5000, 30, 'fil + recherche']],
            'structure' => [['Mensuel', 20000, 30, 'par mois'], ['Saison', 100000, 182, '6 mois, 1 mois offert'], ['Annuel', 180000, 365, '3 mois offerts']],
            'montage' => [
                ['Montage essentiel', 5000, 0, 'Une compilation d’1 min 30 : meilleures actions, ralentis, ton nom et ton poste à l’écran.'],
                ['Montage pro', 10000, 0, 'Jusqu’à 2 min 30, sous-titres français et anglais, ta carte de joueur à l’écran, musique libre de droits.'],
                ['Pack recrutement', 15000, 0, 'Montage pro + 3 formats (fil, WhatsApp, présentation clubs) + 7 jours de Boost offerts.'],
            ],
        ],
    ],
    // Analyse automatique des images (nudité) : https://sightengine.com — sans clés, la protection repose
    // sur les mots interdits, les signalements et la validation des vidéos des nouveaux comptes.
    'sightengine' => [
        'user' => env('SIGHTENGINE_USER', ''),
        'secret' => env('SIGHTENGINE_SECRET', ''),
    ],

    // ---- Langues proposées sur le site (le français est la langue de référence)
    'locales' => ['fr' => 'Français', 'en' => 'English'],

    // Vrais résultats en temps réel (API-Football, https://www.api-football.com). Sans clé : classement réel
    // de la dernière saison seulement. Les durées de cache protègent le quota du fournisseur.
    // ---- Étoile bleue : affichée à côté du nom quand le sportif atteint ce nombre d'abonnés
    'badge_followers' => (int) (env('TERRAIN_BADGE_FOLLOWERS') ?: 1000),
    // ---- Sous-titres et titres traduits automatiquement (facultatif, https://platform.openai.com)
    'openai' => [
        'key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],
    // ---- Comptes sans Supabase : « reels » = vrais comptes enregistrés dans la base du site (inscription, messages réels),
    //      « demo » = comptes d'exemple en un clic. Avec SUPABASE_URL, ce réglage est ignoré.
    'accounts' => env('TERRAIN_COMPTES', 'reels'),
    // Profils d'exemple (« Profil fictif ») affichés à côté des vrais membres pour remplir le fil
    'example_profiles' => false,   // plus de profils d'exemple : uniquement les vrais membres
    // ---- Notifications sur le téléphone même site fermé (Web Push). Clés créées par « php artisan terrain:cles-push ».
    'push' => [
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
        'subject' => env('VAPID_SUBJECT') ?: 'mailto:frikip@gmail.com',   // mailto:ton@email ou adresse du site
    ],
    // ---- Offres pour les structures et services (prix en GNF, multiples de 5) : [libellé, prix, durée en jours, précision]
    'offers' => [
        // Abonnement « Structure » : académies et petits clubs (page mise en avant, contacts illimités, statistiques)
        'structure' => [['Mensuel', 300000, 30, 'par mois'], ['Saison', 1500000, 182, '6 mois, 1 mois offert'], ['Annuel', 2700000, 365, '3 mois offerts']],
        // Montage vidéo réalisé par l'équipe (durée 0 : achat unique)
        'montage' => [
            ['Montage essentiel', 75000, 0, 'Une compilation d’1 min 30 : meilleures actions, ralentis, ton nom et ton poste à l’écran.'],
            ['Montage pro', 150000, 0, 'Jusqu’à 2 min 30, sous-titres français et anglais, ta carte de joueur à l’écran, musique libre de droits.'],
            ['Pack recrutement', 250000, 0, 'Montage pro + 3 formats (fil, WhatsApp, présentation clubs) + 7 jours de Boost offerts.'],
        ],
    ],
    // Boutique partenaire pour l'impression (cartes de joueur, maillots, T-shirts, bâches)
    // Boutique partenaire pour l'impression (cartes de joueur, maillots, T-shirts, bâches)
    'shop_url' => env('TERRAIN_BOUTIQUE_URL', 'https://www.fabara-boutique.com'),
    'shop_name' => env('TERRAIN_BOUTIQUE_NOM', 'Fabara'),
    // ---- Matchs en direct trouvés automatiquement sur YouTube (chaînes officielles, intégration autorisée).
    // Clé gratuite : https://console.cloud.google.com > « YouTube Data API v3 » > Identifiants > Clé API.
    // ---- Diffusion en direct par Frikip (payant) : https://www.mux.com — sans clés, les clubs collent un lien YouTube/Facebook.
    'mux' => [
        'token_id' => env('MUX_TOKEN_ID', ''),
        'token_secret' => env('MUX_TOKEN_SECRET', ''),
        'webhook_secret' => env('MUX_WEBHOOK_SECRET', ''),
        'latency' => env('MUX_LATENCE', 'low'),   // low (≈5 s), reduced (≈12 s) ou standard (≈30 s, le moins cher en données)
    ],
    // ---- API d'un diffuseur partenaire (flux des matchs sous licence) : voir app/Terrain/Growth/PartnerStreams.php
    'live_partner' => [
        'name' => env('LIVE_PARTNER_NAME', ''),
        'url' => env('LIVE_PARTNER_URL', ''),
        'key' => env('LIVE_PARTNER_KEY', ''),
        'header' => env('LIVE_PARTNER_HEADER', 'Authorization'),
        'field' => env('LIVE_PARTNER_FIELD', 'url'),
    ],
    'youtube' => [
        'key' => env('YOUTUBE_API_KEY', ''),
    ],

    'api_football' => [
        'key' => env('API_FOOTBALL_KEY', ''),
        // Compétitions suivies : 2 Ligue des champions, 39 Premier League, 140 Liga, 135 Serie A,
        // 78 Bundesliga, 61 Ligue 1 (France), 12 Ligue des champions CAF, 3 Ligue Europa, 6 CAN, 1 Coupe du monde
        'leagues' => env('API_FOOTBALL_LEAGUES', '2,39,140,135,78,61,12'),
        // Championnat national ajouté automatiquement (nom du pays en anglais, vide pour aucun)
        'country' => env('API_FOOTBALL_COUNTRY', 'Guinea'),
        'season' => env('API_FOOTBALL_SEASON', ''),   // vide = saison en cours de chaque compétition
        'live_ttl' => (int) env('API_FOOTBALL_LIVE_TTL', 60),    // secondes entre deux mises à jour du direct
        'idle_ttl' => (int) env('API_FOOTBALL_IDLE_TTL', 900),   // secondes entre deux mises à jour des matchs du jour
    ],
    'timezone' => env('TERRAIN_TIMEZONE', 'Africa/Conakry'),

    // Mot de passe de l'espace admin (/admin). Vide = espace admin désactivé.
    'admin_password' => env('TERRAIN_ADMIN_PASSWORD', ''),

    // E-mail qui reçoit une alerte à chaque nouvelle demande de suppression (facultatif).
    'notify_email' => env('TERRAIN_NOTIFY_EMAIL', ''),
];
