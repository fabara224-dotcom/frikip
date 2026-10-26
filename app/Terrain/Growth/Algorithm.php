<?php

namespace App\Terrain\Growth;

use App\Terrain\DataSource;
use App\Terrain\Region;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * L'algorithme de Frikip, réglable dans l'espace admin (Admin > Algorithme), sans toucher au code :
 *  1. il CLASSE les sportifs : score de 0 à 100 à partir de signaux réels ;
 *  2. il CHOISIT ce que chaque visiteur voit dans le fil (pays, sport, abonnements, nouveautés) ;
 *  3. ses seuils de MODÉRATION (signalements, avertissements, suspensions, analyse d'image) sont lus par ContentGuard.
 */
class Algorithm
{
    /** Réglages : clé => [valeur de départ, type, groupe, libellé, aide] */
    public const SETTINGS = [
        // ---- Classement des sportifs (poids de 0 à 10)
        'w_views' => [2, 'weight', 'classement', 'Vues des vidéos', 'Nombre de fois où ses vidéos ont été regardées'],
        'w_likes' => [3, 'weight', 'classement', 'J’aime', 'J’aime reçus sur ses vidéos'],
        'w_shares' => [3, 'weight', 'classement', 'Partages', 'Vidéos partagées sur WhatsApp, Facebook…'],
        'w_comments' => [2, 'weight', 'classement', 'Commentaires', 'Commentaires reçus'],
        'w_followers' => [3, 'weight', 'classement', 'Abonnés', 'Personnes qui le suivent'],
        'w_profile' => [2, 'weight', 'classement', 'Profil complet', 'Fiche du sport, ville, club, taille, poids remplis'],
        'w_verified' => [2, 'weight', 'classement', 'Identité vérifiée', 'Pièce d’identité validée par l’équipe'],
        'w_regularity' => [1, 'weight', 'classement', 'Régularité', 'Vidéos publiées ces 30 derniers jours'],
        'w_challenges' => [2, 'weight', 'classement', 'Défis gagnés', 'Victoires au défi de la semaine'],
        'penalty_warning' => [15, 'int', 'classement', 'Pénalité par avertissement', 'Points retirés au score pour chaque avertissement en cours'],
        // ---- Ce que les gens voient dans le fil
        'country_priority' => ['forte', 'level', 'fil', 'Priorité au pays du visiteur', 'Les vidéos du pays du visiteur passent avant le reste de la région'],
        'sport_priority' => ['moyenne', 'level', 'fil', 'Priorité au sport du visiteur', 'Pour un sportif : les vidéos de son sport passent avant'],
        'learn_sport' => ['moyenne', 'level', 'fil', 'Priorité aux sports que le visiteur regarde', 'Le fil apprend : quelqu’un qui regarde surtout du basket voit plus de basket'],
        'hidden_sports' => ['', 'words', 'fil', 'Sports cachés du fil', 'Un sport par ligne (ex. Lutte). Ses vidéos restent sur les profils et dans la recherche.'],
        'follow_priority' => ['forte', 'level', 'fil', 'Priorité aux comptes suivis', 'Les sportifs que le visiteur suit passent avant'],
        'fresh_hours' => [48, 'int', 'fil', 'Durée « nouveauté » (heures)', 'Une vidéo récente est mise en avant pendant ce temps'],
        'paid_boost' => [true, 'bool', 'fil', 'Le Boost payant met en tête', 'Les sportifs qui ont payé un Boost passent en premier'],
        'diversity' => [4, 'int', 'fil', 'Écart entre deux vidéos du même sportif', 'Nombre de vidéos d’autres sportifs entre deux vidéos d’un même sportif'],
        'hide_score_below' => [0, 'int', 'fil', 'Cacher les sportifs dont le score est sous', '0 = ne cacher personne ; utile contre les comptes sanctionnés'],
        // ---- Modération
        'report_threshold' => [3, 'int', 'moderation', 'Signalements avant masquage automatique', 'Nombre de personnes différentes qui signalent une vidéo avant qu’elle soit retirée en attendant l’admin'],
        'warnings_before_suspension' => [2, 'int', 'moderation', 'Avertissements avant suspension', ''],
        'suspension_days' => [7, 'int', 'moderation', 'Durée de la suspension (jours)', ''],
        'warnings_before_ban' => [3, 'int', 'moderation', 'Avertissements avant blocage définitif', ''],
        'premoderate_new' => [false, 'bool', 'moderation', 'Valider les vidéos des nouveaux comptes', 'Les vidéos des comptes récents attendent l’accord d’un admin avant d’être publiques'],
        'new_account_days' => [7, 'int', 'moderation', 'Un compte est « nouveau » pendant (jours)', ''],
        'image_check' => [true, 'bool', 'moderation', 'Analyse automatique des images', 'Bloque la nudité avant publication (clés Sightengine nécessaires)'],
        'nudity_threshold' => [0.6, 'float', 'moderation', 'Seuil de l’analyse d’image (0 à 1)', 'Plus il est bas, plus l’analyse est sévère'],
        // ---- Défis (Admin > Défis)
        'defi_boost_days' => [7, 'int', 'defis', 'Jours de Boost offerts au gagnant', 'Le gagnant passe en tête du fil pendant ces jours. 0 = pas de Boost'],
        'defi_prize' => ['', 'text', 'defis', 'Lot du défi de la semaine', 'Ex. « Un maillot Fabara ». Vide = seulement le Boost'],
        'defi_vote_age_hours' => [0, 'int', 'defis', 'Âge minimum d’un compte pour voter (heures)', 'Contre les faux comptes créés pour voter. 0 = tout le monde'],
        'defi_in_feed' => [true, 'bool', 'defis', 'Annoncer les défis dans le fil', 'Une étiquette en haut du fil mène au défi en cours'],
        // ---- Publicités (Admin > Publicités)
        'ads_enabled' => [true, 'bool', 'pub', 'Afficher les publicités', 'Coupe toutes les pubs du site d’un seul geste'],
        'ads_every' => [6, 'int', 'pub', 'Une pub toutes les … vidéos du fil', 'Entre 3 et 30 ; plus le nombre est grand, moins il y a de pubs'],
        'ads_guests' => [true, 'bool', 'pub', 'Montrer les pubs aux visiteurs sans compte', ''],
        'ads_live' => [true, 'bool', 'pub', 'Une pub avant chaque match en direct', 'Le spectateur peut la passer après 5 secondes'],
        // ---- Matchs en direct (Admin > Direct)
        'yt_enabled' => [true, 'bool', 'direct', 'Chercher les matchs en direct sur YouTube', 'Directs des chaînes officielles qui autorisent l’intégration (clé YOUTUBE_API_KEY nécessaire)'],
        'yt_queries' => ["football en direct Guinée\nLigue 1 Guinée direct\nfootball direct Sénégal\nLigue 1 Côte d'Ivoire direct\nbasket direct Afrique\nlutte sénégalaise direct\nfootball live Ghana\nNPFL live", 'words', 'direct', 'Recherches de directs', 'Une recherche par ligne'],
        'yt_channels' => ['', 'words', 'direct', 'Chaînes YouTube suivies', 'Une adresse ou un identifiant de chaîne par ligne (fédération, ligue, club, télé). Leurs directs sont toujours ajoutés.'],
        'yt_blocklist' => ["highlights\nrésumé\nfifa 2\nefootball\npes 20\nfc 2\nsimulation\ngameplay\nprediction\npronostic\nbet", 'words', 'direct', 'Mots qui écartent un direct', 'Évite les jeux vidéo, pronostics et faux directs'],
        'yt_auto_publish' => [false, 'bool', 'direct', 'Publier les directs trouvés sans validation', 'Sinon ils attendent ton accord dans Admin > Direct'],
        'live_in_feed' => [true, 'bool', 'direct', 'Montrer les matchs en direct dans le fil', 'Une carte « En direct » s’insère entre les vidéos'],
        'live_auto_verified' => [true, 'bool', 'direct', 'Publication directe pour les clubs vérifiés', 'Les clubs vérifiés ou avec Structure Pro publient leurs directs sans attendre l’admin'],
        // ---- Lien unique du partenaire de diffusion (Admin > Diffuseurs)
        'partner_on' => [false, 'bool', 'partenaire', 'Activer le lien du partenaire', 'Le lien est proposé sur tous les matchs, sur la page « En direct » et en haut du fil'],
        'partner_name' => ['', 'text', 'partenaire', 'Nom du partenaire', 'Ex. « New World TV ». Affiché à côté du lecteur'],
        'partner_url' => ['', 'url', 'partenaire', 'Lien unique pour tous les matchs', 'Le lien donné par ton partenaire (https://…) : lecteur, chaîne en direct, flux .m3u8 ou page de ses matchs'],
        'partner_contract' => [false, 'bool', 'partenaire', 'Accord écrit signé : la vidéo se regarde dans Frikip', 'Sans accord, le bouton ouvre le site du partenaire dans un nouvel onglet'],
        'partner_note' => ['', 'text', 'partenaire', 'Précision affichée (facultatif)', 'Ex. « Gratuit » ou « Avec abonnement »'],
        // ---- Mots interdits (un par ligne) : refusés dès l'envoi dans les titres, commentaires, messages, profils et offres
        'words_nudite' => ["porno\nporn\npornographie\nxxx\nsexe gratuit\nsex tape\nnudes\nonlyfans\npornhub\nxvideos\nplan cul\nescort", 'words', 'mots', 'Nudité et pornographie', ''],
        'words_haine' => ["sale race\nsale noir\nsale blanc\nva mourir\nsous-homme", 'words', 'mots', 'Propos haineux', ''],
        'words_violence' => ["je vais te tuer\non va te tuer\nje vais te frapper\nte faire la peau", 'words', 'mots', 'Violence et menaces', ''],
        'words_arnaque' => ["frais d'essai\nfrais de test\nfrais de dossier\npayer pour l'essai\npaye pour l'essai\nfrais de visa\nvisa garanti\ncontrat garanti\nwestern union\nenvoie l'argent\nenvoyer de l'argent\nmoneygram", 'words', 'mots', 'Arnaques (argent pour un essai, un visa, un transfert)', ''],
        'words_spam' => ["gagnez de l'argent\nargent facile\ninvestissement garanti\ndoublez votre argent\nbit.ly\ncliquez ici", 'words', 'mots', 'Spam et publicité', ''],
    ];

    public const LEVELS = ['aucune' => 0, 'faible' => 15, 'moyenne' => 35, 'forte' => 70];

    public const RULES = [
        'nudite' => 'Nudité ou pornographie', 'mineurs' => 'Mise en danger de mineurs', 'violence' => 'Violence ou menaces',
        'haine' => 'Propos haineux', 'arnaque' => 'Arnaque (argent demandé pour un essai, un visa, un transfert)',
        'spam' => 'Spam ou publicité', 'faux_profil' => 'Faux profil', 'signalements' => 'Signalé par plusieurs membres', 'autre' => 'Autre manquement aux règles',
    ];

    /** Règles qui entraînent un blocage immédiat et définitif (non modifiable). */
    public const ZERO_TOLERANCE = ['nudite', 'mineurs'];

    private ?array $values = null;

    private array $scores = [];

    // ------------------------------------------------------------------ réglages

    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }
        $stored = [];
        try {
            $stored = Cache::remember('frikip.algo', 300, fn () => DB::table('algo_settings')->pluck('value', 'key')->all());
        } catch (Throwable) {
        }
        $out = [];
        foreach (self::SETTINGS as $k => [$default, $type]) {
            $out[$k] = array_key_exists($k, $stored) ? self::cast(json_decode($stored[$k], true), $type, $default) : $default;
        }

        return $this->values = $out;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? (self::SETTINGS[$key][0] ?? null);
    }

    private static function cast(mixed $v, string $type, mixed $default): mixed
    {
        return match ($type) {
            'weight' => max(0, min(10, (int) $v)),
            'int' => max(0, (int) $v),
            'float' => max(0.05, min(1, (float) $v)),
            'bool' => (bool) $v,
            'level' => array_key_exists((string) $v, self::LEVELS) ? (string) $v : $default,
            'words' => is_string($v) ? $v : $default,
            'text' => is_string($v) ? mb_substr(trim($v), 0, 160) : $default,
            'url' => is_string($v) && (trim($v) === '' || PartnerLink::valid(trim($v))) ? mb_substr(trim($v), 0, 1000) : $default,
            default => $v,
        };
    }

    /** Enregistre des réglages (effet immédiat sur tout le site). @return array<string, array{0: mixed, 1: mixed}> changements [avant, après] */
    public function save(array $input, ?string $group = null): array
    {
        $before = $this->all();
        $changes = [];
        foreach (self::SETTINGS as $k => [$default, $type, $g]) {
            if ($group !== null && $g !== $group) {
                continue;   // une case non cochée d'une autre section ne doit rien changer
            }
            if ($type === 'bool') {
                $v = ! empty($input[$k]);
            } elseif (! array_key_exists($k, $input)) {
                continue;
            } else {
                $v = $input[$k];
                if ($type === 'words') {
                    $lines = preg_split('/\R/u', (string) $v) ?: [];
                    $v = implode("\n", array_values(array_unique(array_filter(array_map(fn ($l) => mb_substr(trim($l), 0, 80), $lines)))));
                }
                $v = self::cast($v, $type, $default);
            }
            if ($v !== $before[$k]) {
                DB::table('algo_settings')->updateOrInsert(['key' => $k], ['value' => json_encode($v, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
                $changes[$k] = [$before[$k], $v];
            }
        }
        Cache::forget('frikip.algo');
        $this->values = null;
        $this->scores = [];

        return $changes;
    }

    public function reset(string $group): void
    {
        $keys = array_keys(array_filter(self::SETTINGS, fn ($s) => $s[2] === $group));
        DB::table('algo_settings')->whereIn('key', $keys)->delete();
        Cache::forget('frikip.algo');
        $this->values = null;
    }

    // ------------------------------------------------------------------ 1. classement

    /** Avertissements en cours par profil sportif et par compte. */
    private function warnings(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        try {
            $byUser = DB::table('member_strikes')->whereNull('revoked_at')->selectRaw('user_key, count(*) n')->groupBy('user_key')->pluck('n', 'user_key')->all();
            if ($byUser) {
                $athletes = DB::table('members')->whereIn('user_key', array_keys($byUser))->whereNotNull('athlete_id')->pluck('athlete_id', 'user_key')->all();
                foreach ($byUser as $u => $n) {
                    $map[$u] = (int) $n;
                    if (isset($athletes[$u])) {
                        $map[$athletes[$u]] = (int) $n;
                    }
                }
            }
        } catch (Throwable) {
        }

        return $map;
    }

    private function wins(): array
    {
        static $wins = null;
        if ($wins !== null) {
            return $wins;
        }
        $wins = [];
        try {
            $wins = DB::table('challenges')->join('challenge_entries', 'challenge_entries.id', '=', 'challenges.winner_entry_id')
                ->selectRaw('challenge_entries.athlete_id a, count(*) n')->groupBy('challenge_entries.athlete_id')->pluck('n', 'a')->all();
        } catch (Throwable) {
        }

        return $wins;
    }

    /** Part de la fiche remplie (0 à 1). */
    public static function completeness(array $a): float
    {
        $basics = array_filter([$a['city'] ?? '', $a['height'] ?? '', $a['weight'] ?? '', $a['club'] ?? '', $a['specialty'] ?? ''], fn ($v) => $v && $v !== '–' && $v !== 'Sans club');
        $fields = $a['fields'] ?? [];
        $filled = count(array_filter($fields, fn ($v) => $v !== '–' && $v !== '' && $v !== null));
        $parts = count($basics) / 5 * 0.4 + ($fields ? $filled / count($fields) : 0) * 0.4 + (count($a['videos'] ?? []) > 0 ? 0.2 : 0);

        return round(min(1, $parts), 3);
    }

    /**
     * Score du sportif, de 0 à 100, et le détail de chaque signal (pour l'espace admin).
     *
     * @return array{score: float, parts: array<string, array{0: float, 1: int}>, warnings: int}
     */
    public function explain(array $a): array
    {
        $s = $this->all();
        $vids = $a['videos'] ?? [];
        $sum = fn (string $k) => array_sum(array_map(fn ($v) => (int) ($v[$k] ?? 0), $vids));
        $recent = count(array_filter($vids, fn ($v) => ! empty($v['created_at']) && strtotime($v['created_at']) > time() - 30 * 86400));
        $norm = [
            'w_views' => min(1, log10(1 + $sum('views')) / 5),          // 100 000 vues = maximum
            'w_likes' => min(1, log10(1 + $sum('likes')) / 4),          // 10 000 j'aime
            'w_shares' => min(1, log10(1 + $sum('shares')) / 3),        // 1 000 partages
            'w_comments' => min(1, log10(1 + $sum('comments')) / 3),
            'w_followers' => min(1, log10(1 + (int) ($a['followers'] ?? 0)) / 4),
            'w_profile' => self::completeness($a),
            'w_verified' => ! empty($a['verified']) ? 1 : 0,
            'w_regularity' => min(1, $recent / 4),
            'w_challenges' => min(1, ($this->wins()[$a['id']] ?? 0) / 3),
        ];
        $total = 0;
        $weights = 0;
        $parts = [];
        foreach ($norm as $k => $n) {
            $w = (int) $s[$k];
            $weights += $w;
            $total += $w * $n;
            $parts[$k] = [round($n, 3), $w];
        }
        $warn = $this->warnings()[$a['id']] ?? $this->warnings()[$a['contact_id'] ?? ''] ?? 0;
        $score = $weights ? $total / $weights * 100 : 0;
        // Les profils d'exemple ont une note de départ (leur fiche) qui ne remplace pas les vrais signaux
        if (! empty($a['demo']) && isset($a['score'])) {
            $score = $score * 0.6 + (float) $a['score'] * 0.4;
        }
        $score -= $warn * (int) $s['penalty_warning'];

        return ['score' => round(max(0, min(100, $score)), 1), 'parts' => $parts, 'warnings' => $warn];
    }

    public function score(array $a): float
    {
        return $this->scores[$a['id']] ??= $this->explain($a)['score'];
    }

    // ------------------------------------------------------------------ 2. ce que chaque visiteur voit

    /**
     * Ordonne les vidéos du fil pour ce visiteur.
     *
     * @param  list<array{athlete: array, video: array}>  $items
     */
    public function orderFeed(array $items, ?array $viewer = null): array
    {
        $s = $this->all();
        $data = app(DataSource::class);
        $viewer ??= $data->user();
        $country = Region::viewer();
        $mySport = null;
        if (! empty($viewer['athlete_id'])) {
            $mySport = $data->athlete($viewer['athlete_id'])['sport'] ?? null;
        }
        $following = [];
        try {
            $following = $viewer ? $data->following() : [];
        } catch (Throwable) {
        }
        $hidden = array_map('mb_strtolower', array_filter(array_map('trim', preg_split('/\R/u', (string) $s['hidden_sports']))));
        // Sports regardés pendant la visite (vues de 2 s ou plus) : le fil s'adapte
        $watched = (array) session('frikip.sports', []);
        $watchedTotal = array_sum($watched);
        $seed = (string) (session('frikip.seed') ?? tap((string) random_int(1, PHP_INT_MAX), fn ($v) => session(['frikip.seed' => $v])));
        $fresh = time() - (int) $s['fresh_hours'] * 3600;
        $out = [];
        foreach ($items as $it) {
            $a = $it['athlete'];
            $v = $it['video'];
            if ($hidden && in_array(mb_strtolower((string) $a['sport']), $hidden, true) && ($viewer['athlete_id'] ?? null) !== $a['id']) {
                continue;
            }
            $score = $this->score($a);
            if ($s['hide_score_below'] > 0 && $score < $s['hide_score_below'] && ($viewer['athlete_id'] ?? null) !== $a['id']) {
                continue;
            }
            $video = min(30, log10(1 + (int) ($v['likes'] ?? 0)) * 6 + log10(1 + (int) ($v['shares'] ?? 0)) * 6 + log10(1 + (int) ($v['views'] ?? 0)) * 3);
            $rank = $score + $video;
            if (($a['country'] ?? null) === $country) {
                $rank += self::LEVELS[$s['country_priority']];
            }
            if ($mySport && $a['sport'] === $mySport) {
                $rank += self::LEVELS[$s['sport_priority']];
            }
            if ($watchedTotal >= 3 && ! empty($watched[$a['sport']])) {
                $rank += self::LEVELS[$s['learn_sport']] * 1.5 * $watched[$a['sport']] / $watchedTotal;
            }
            if (in_array($a['id'], $following, true)) {
                $rank += self::LEVELS[$s['follow_priority']];
            }
            if (! empty($v['created_at']) && strtotime($v['created_at']) > $fresh) {
                $rank += 60;
            }
            if (! empty($s['real_first']) && empty($a['demo'])) {
                $rank += 40;
            }
            if (! empty($v['voice_demo'])) {
                $rank += 20;   // vidéos d'exemple avec son et sous-titres
            }
            $rank *= 0.8 + (crc32($seed.$v['id']) % 1000) / 1000 * 0.4;   // variété : ±20 %, stable pendant la visite
            if ($s['paid_boost'] && ! empty($a['boosted'])) {
                $rank += 10000;
            }
            $it['rank'] = $rank;
            $out[] = $it;
        }
        usort($out, fn ($x, $y) => $y['rank'] <=> $x['rank']);

        return $this->spread($out, max(0, (int) $s['diversity']));
    }

    /**
     * Intérêt du visiteur pour chaque sport (sports regardés + sport du sportif connecté), de 0 à 1.
     * Sert à trier tout ce qui n'est pas le fil (matchs, directs) sans filtre à choisir.
     */
    public function sportAffinity(): array
    {
        $w = array_map('floatval', (array) session('frikip.sports', []));
        $total = array_sum($w);
        $out = [];
        foreach ($w as $sp => $n) {
            $out[$sp] = $total ? $n / $total : 0;
        }
        try {
            $me = app(DataSource::class)->user();
            if (! empty($me['athlete_id']) && ($sp = app(DataSource::class)->athlete($me['athlete_id'])['sport'] ?? null)) {
                $out[$sp] = ($out[$sp] ?? 0) + 0.5;
            }
        } catch (Throwable) {
        }
        foreach (array_filter(array_map('trim', preg_split('/\R/u', (string) $this->get('hidden_sports')))) as $h) {
            $out[$h] = -1;
        }

        return $out;
    }

    /** Trie une liste (matchs, directs) : sports préférés du visiteur d'abord, ordre d'origine conservé sinon. */
    public function bySport(array $list, string $key = 'sport'): array
    {
        $aff = $this->sportAffinity();
        if (! $aff) {
            return $list;
        }
        $i = 0;
        $keyed = array_map(function ($x) use (&$i, $aff, $key) {
            $sp = is_array($x) ? ($x[$key] ?? '') : ($x->{$key} ?? '');

            return [$aff[$sp] ?? 0, $i++, $x];
        }, $list);
        usort($keyed, fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_map(fn ($k) => $k[2], $keyed);
    }

    /** Évite d'enchaîner deux vidéos du même sportif à moins de $gap positions d'écart. */
    private function spread(array $items, int $gap): array
    {
        if ($gap < 1) {
            return array_values($items);
        }
        $out = [];
        $recent = [];
        $items = array_values($items);
        while ($items) {
            $pick = 0;
            foreach ($items as $k => $it) {
                if (! in_array($it['athlete']['id'], $recent, true)) {
                    $pick = $k;
                    break;
                }
                if ($k > 30) {
                    break;
                }
            }
            $it = $items[$pick];
            array_splice($items, $pick, 1);
            $out[] = $it;
            $recent[] = $it['athlete']['id'];
            if (count($recent) > $gap) {
                array_shift($recent);
            }
        }

        return $out;
    }
}
