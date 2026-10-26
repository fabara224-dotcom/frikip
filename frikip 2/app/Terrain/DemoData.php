<?php

namespace App\Terrain;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * MODE DÉMO : mêmes données d'exemple que l'app Flutter.
 * Les actions du visiteur (j'aime, commentaires, messages, offres…) sont gardées dans sa session.
 * Les matchs « en direct » avancent selon l'heure et recommencent en boucle.
 */
class DemoData implements DataSource
{
    public const DEMO_VIDEO = 'https://flutter.github.io/assets-for-api-docs/assets/videos/butterfly.mp4';

    public const RECRUITERS = [
        'r1' => ['id' => 'r1', 'name' => 'AS Kaloum – Recrutement', 'sport' => 'Football', 'verified' => true],
    ];

    public function isDemo(): bool
    {
        return true;
    }

    // ------------------------------------------------------------------ données de base

    /**
     * 326 sportifs FICTIFS et 1000 compilations illustrées (resources/data/demo-athletes.json,
     * vidéos dans public/demo/videos et images dans public/demo/posters).
     */
    private static ?array $base = null;

    protected static function baseAthletes(): array
    {
        if (self::$base !== null) {
            return self::$base;
        }
        $rows = json_decode((string) file_get_contents(resource_path('data/demo-athletes.json')), true) ?: [];
        $videoBase = asset('demo/videos');
        $posterBase = asset('demo/posters');
        // Vraies vidéos (Pixabay / Pexels) si « php artisan terrain:videos-reelles » a été lancé
        $real = is_file($f = storage_path('app/demo-videos-reelles.json')) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        // Quelques vidéos d'exemple avec un commentaire parlé (voix de synthèse) pour montrer le son et les sous-titres
        $voices = json_decode((string) @file_get_contents(resource_path('data/demo-voix.json')), true) ?: [];

        return self::$base = array_map(function (array $a) use ($videoBase, $posterBase, $real, $voices) {
            $a['demo'] = true;
            $a['videos'] = array_map(function (array $v) use ($a, $videoBase, $posterBase, $real, $voices) {
                // Les chiffres (vues, j'aime, commentaires) ne sont plus inventés : ils sont comptés pour de vrai (Growth\Engagement).
                $video = [
                    'id' => $v[0], 'athlete_id' => $a['id'], 'title' => $v[1], 'caption' => $v[2], 'duration' => '0:06',
                    'url' => "{$videoBase}/{$v[0]}.mp4", 'poster' => "{$posterBase}/{$v[0]}.jpg", 'views' => 0, 'likes' => 0,
                    'lang' => 'none', 'has_audio' => false,
                ];
                if ($vo = $voices[$v[0]] ?? null) {
                    $video['url'] = asset($vo['file']);
                    $video['duration'] = $vo['duration'];
                    $video['lang'] = $vo['lang'];
                    $video['has_audio'] = true;
                    $video['voice_demo'] = true;

                    return $video;
                }
                if ($r = $real[$v[0]] ?? null) {
                    $local = ! empty($r['local']);
                    $video['url'] = $local ? asset($r['video']) : $r['video'];
                    $video['poster'] = $local ? asset($r['poster']) : $r['poster'];
                    $video['duration'] = sprintf('%d:%02d', intdiv($r['duration'], 60), $r['duration'] % 60);
                    $video['credit'] = $r['credit'];
                    $video['credit_url'] = $r['credit_url'];
                    $video['source_url'] = $r['page'];
                    $video['source_name'] = $r['source'] ?? 'Pexels';
                }

                return $video;
            }, $a['videos']);

            return $a;
        }, $rows);
    }

    private const STANDINGS = [
        ['AS Kaloum U20', 8, 6, 1, 1, 17, 6], ['Académie Fouta FC', 8, 5, 2, 1, 13, 5], ['Horoya AC U20', 8, 5, 1, 2, 15, 8],
        ['Milo FC U20', 8, 3, 2, 3, 10, 11], ['Santoba FC U20', 8, 1, 2, 5, 6, 14],
    ];

    // ------------------------------------------------------------------ session

    protected function s(string $key, mixed $default = []): mixed
    {
        return session('demo.'.$key, $default);
    }

    protected function put(string $key, mixed $value): void
    {
        session(['demo.'.$key => $value]);
    }

    // ------------------------------------------------------------------ comptes

    public function signIn(string $email, string $password): array
    {
        $recruiter = Str::contains(Str::lower($email), ['club', 'recrut', 'agent']);

        return $this->demoLogin($recruiter ? 'recruiter' : 'athlete');
    }

    /** Connexion en un clic en mode démo. */
    public function demoLogin(string $role): array
    {
        $user = $role === 'recruiter'
            ? ['id' => 'r1', 'role' => 'recruiter', 'email' => 'recrutement@askaloum.exemple', 'name' => 'AS Kaloum', 'athlete_id' => null, 'premium' => false, 'verified' => true]
            : ['id' => 'u-a1', 'role' => 'athlete', 'email' => 'mamadou@exemple.gn', 'name' => 'Mamadou Camara', 'athlete_id' => 'a1', 'premium' => false, 'verified' => true];
        session(['terrain.user' => $user]);

        return $user;
    }

    public function signUp(string $email, string $password, string $role, array $profile): array
    {
        if ($role === 'recruiter') {
            $user = ['id' => 'r-'.Str::random(6), 'role' => 'recruiter', 'email' => $email, 'name' => $profile['name'] ?? 'Recruteur', 'athlete_id' => null, 'premium' => false, 'verified' => false];
        } else {
            $id = 'n-'.Str::random(6);
            $extra = $this->s('athletes', []);
            $extra[$id] = [
                'id' => $id, 'name' => $profile['name'], 'sport' => $profile['sport'], 'age' => (int) $profile['age'],
                'country' => $profile['country'], 'city' => $profile['city'] ?: '–', 'height' => '–', 'weight' => '–',
                'level' => 'Amateur', 'club' => 'Sans club', 'availability' => 'Immédiate', 'verified' => false, 'score' => 60,
                'fields' => $profile['fields'] ?? [], 'videos' => [],
            ];
            $this->put('athletes', $extra);
            $user = ['id' => 'u-'.$id, 'role' => 'athlete', 'email' => $email, 'name' => $profile['name'], 'athlete_id' => $id, 'premium' => false, 'verified' => false];
        }
        session(['terrain.user' => $user]);

        return $user;
    }

    public function signOut(): void
    {
        session()->forget('terrain.user');
    }

    public function user(): ?array
    {
        return session('terrain.user');
    }

    public function deleteAccount(): void
    {
        $u = $this->user();
        if ($u && $u['athlete_id'] && Str::startsWith($u['athlete_id'], 'n-')) {
            $extra = $this->s('athletes', []);
            unset($extra[$u['athlete_id']]);
            $this->put('athletes', $extra);
        }
        session()->forget(['terrain.user', 'demo']);
    }

    // ------------------------------------------------------------------ sportifs et vidéos

    /** Vidéos publiées sur le site en mode démo (gardées pour tous les visiteurs). */
    protected function publishedVideos(): array
    {
        $out = [];
        try {
            foreach (\Illuminate\Support\Facades\DB::table('local_videos')->orderByDesc('created_at')->get() as $r) {
                $out[$r->athlete_id][] = [
                    'id' => $r->id, 'athlete_id' => $r->athlete_id, 'title' => $r->title, 'caption' => $r->caption ?: $r->title,
                    'duration' => $r->duration ?: '', 'url' => str_starts_with($r->url, 'http') ? $r->url : asset($r->url),
                    'poster' => $r->poster ? asset($r->poster) : null, 'views' => 0, 'likes' => 0, 'created_at' => (string) $r->created_at,
                ];
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    protected ?array $cache = null;

    /** Profils avant enrichissement : exemples + profils créés pendant la visite (remplacé par LocalData). */
    protected function sourceAthletes(): array
    {
        return array_merge(self::baseAthletes(), array_values($this->s('athletes', [])));
    }

    protected function allAthletes(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $list = $this->sourceAthletes();
        $published = $this->publishedVideos();
        $boosted = app(\App\Terrain\Growth\Members::class)->boostedAthletes();

        foreach ($list as &$a) {
            $a['contact_id'] = $a['id'];
            $a['videos'] = array_merge($published[$a['id']] ?? [], $a['videos']);
            $a['boosted'] = ($this->s('boost', false) && $a['id'] === ($this->user()['athlete_id'] ?? null)) || in_array($a['id'], $boosted, true);
        }
        unset($a);

        // Vrais chiffres (j'aime, commentaires, partages, vues), abonnés et étoile bleue
        $eng = app(\App\Terrain\Growth\Engagement::class);
        $list = $eng->decorateAthletes($eng->decorateVideos($list, true, $this->user()['id'] ?? null));

        // Décisions du site : badges validés par l'admin, comptes bloqués et vidéos masquées
        return $this->cache = app(\App\Terrain\Growth\Moderation::class)->filterAthletes(app(\App\Terrain\Growth\Verifications::class)->applyToAthletes($list));
    }

    public function athletes(array $filters = []): array
    {
        return Filters::apply($this->allAthletes(), $filters);
    }

    public function athlete(string $id): ?array
    {
        foreach ($this->allAthletes() as $a) {
            if ($a['id'] === $id) {
                return $a;
            }
        }

        return null;
    }

    public function feed(string $sport = 'Tous'): array
    {
        $items = [];
        // Chaque visiteur a son propre ordre (stable pendant sa visite) pour que le fil ne soit pas toujours le même.
        $seed = $this->s('seed', null) ?? tap((string) random_int(1, PHP_INT_MAX), fn ($v) => $this->put('seed', $v));
        foreach ($this->allAthletes() as $a) {
            if ($sport !== 'Tous' && $a['sport'] !== $sport) {
                continue;
            }
            $hero = (int) substr($a['id'], 1) <= 20 ? 2.5 : 1;   // les 20 profils détaillés remontent plus souvent
            foreach ($a['videos'] as $v) {
                // une vidéo publiée il y a moins de 2 jours passe en tête
                if (! empty($v['created_at']) && strtotime($v['created_at']) > time() - 2 * 86400) {
                    $items[] = ['athlete' => $a, 'video' => $v, 'rank' => 2e9 + strtotime($v['created_at'])];
                    continue;
                }
                $jitter = 0.35 + (crc32($seed.$v['id']) % 1000) / 1000 * 1.3;
                // vrais signaux : j'aime, commentaires, partages, vues, abonnés
                $engagement = $v['likes'] * 5 + $v['comments'] * 4 + ($v['shares'] ?? 0) * 6 + $v['views'] * 0.3 + ($a['followers'] ?? 0) * 0.5;
                $rank = ($a['boosted'] ? 1e8 : 0) + ($a['score'] + $engagement + (! empty($v['voice_demo']) ? 40 : 0)) * ($a['verified'] ? 1.1 : 1) * $hero * $jitter;
                $items[] = ['athlete' => $a, 'video' => $v, 'rank' => $rank];
            }
        }
        usort($items, fn ($x, $y) => $y['rank'] <=> $x['rank']);

        return self::spread($items);
    }

    /** Évite d'enchaîner deux vidéos du même sportif à moins de 4 positions d'écart. */
    private static function spread(array $items): array
    {
        $out = [];
        $recent = [];
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
            unset($items[$pick]);
            $items = array_values($items);
            $out[] = $it;
            $recent[] = $it['athlete']['id'];
            if (count($recent) > 3) {
                array_shift($recent);
            }
        }

        return $out;
    }

    public function toggleLike(string $videoId): array
    {
        $eng = app(\App\Terrain\Growth\Engagement::class);
        $liked = $eng->toggleLike($videoId, $this->user()['id']);
        $this->cache = null;

        return ['liked' => $liked, 'likes' => $eng->of($videoId)['likes']];
    }

    public function comments(string $videoId): array
    {
        return app(\App\Terrain\Growth\Engagement::class)->localComments($videoId);
    }

    public function addComment(string $videoId, string $text, ?string $parentId = null): array
    {
        $this->cache = null;

        return app(\App\Terrain\Growth\Engagement::class)->addLocalComment($videoId, $this->user(), $text, $parentId);
    }

    public function comment(string $commentId): ?array
    {
        $c = app(\App\Terrain\Growth\Engagement::class)->findLocalComment($commentId);

        return $c ? ['id' => (string) $c->id, 'video_id' => $c->video_id, 'user_key' => $c->user_key, 'parent_id' => $c->parent_id ? (string) $c->parent_id : null] : null;
    }

    public function deleteComment(string $commentId): bool
    {
        $c = $this->comment($commentId);
        if (! $c || $c['user_key'] !== ($this->user()['id'] ?? null)) {
            return false;
        }
        app(\App\Terrain\Growth\Engagement::class)->deleteLocalComment($commentId);
        $this->cache = null;

        return true;
    }

    public function videoOwner(string $videoId): ?string
    {
        foreach ($this->allAthletes() as $a) {
            foreach ($a['videos'] as $v) {
                if ($v['id'] === $videoId) {
                    return $a['id'];
                }
            }
        }

        return null;
    }

    /** Abonnements gardés dans la base du site : les compteurs d'abonnés sont réels pour tous les visiteurs. */
    public function toggleFollow(string $athleteId): bool
    {
        $key = $this->user()['id'];
        $q = \Illuminate\Support\Facades\DB::table('follows_local')->where('follower_key', $key)->where('athlete_id', $athleteId);
        if ($q->exists()) {
            $q->delete();
            $on = false;
        } else {
            \Illuminate\Support\Facades\DB::table('follows_local')->insertOrIgnore(['follower_key' => $key, 'athlete_id' => $athleteId, 'created_at' => now()]);
            $on = true;
        }
        app(\App\Terrain\Growth\Engagement::class)->reset();
        $this->cache = null;

        return $on;
    }

    public function following(): array
    {
        $key = $this->user()['id'] ?? null;
        if (! $key) {
            return [];
        }
        try {
            return \Illuminate\Support\Facades\DB::table('follows_local')->where('follower_key', $key)->pluck('athlete_id')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** En mode démo, la vidéo envoyée est gardée sur le serveur du site (dossier public/uploads/videos). */
    public function publishVideo(array $meta, ?UploadedFile $file): ?string
    {
        $me = $this->user()['athlete_id'] ?? null;
        if (! $me) {
            return null;
        }
        $id = 'p-'.Str::lower(Str::random(10));
        $url = self::DEMO_VIDEO;
        if ($file) {
            $dir = public_path('uploads/videos');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = $id.'.'.(strtolower($file->getClientOriginalExtension()) ?: 'mp4');
            $file->move($dir, $name);
            $url = 'uploads/videos/'.$name;
        }
        \Illuminate\Support\Facades\DB::table('local_videos')->insert([
            'id' => $id, 'athlete_id' => $me, 'title' => $meta['title'], 'caption' => $meta['caption'] ?? null,
            'competition' => $meta['competition'] ?? null, 'url' => $url, 'duration' => $meta['duration'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->cache = null;

        return $id;
    }

    /** Chemin du fichier vidéo sur le serveur (pour les sous-titres automatiques). */
    public function localVideoFile(string $videoId): ?string
    {
        $url = \Illuminate\Support\Facades\DB::table('local_videos')->where('id', $videoId)->value('url');

        return $url && ! str_starts_with($url, 'http') && is_file(public_path($url)) ? public_path($url) : null;
    }

    // ------------------------------------------------------------------ matchs

    /** Minute de jeu simulée : un match de football dure 3 minutes réelles, puis recommence. */
    private static function liveMinute(int $period, int $length): int
    {
        return min($length, intdiv(time() % $period, max(1, intdiv($period, $length))) + 1);
    }

    public function matches(): array
    {
        $min1 = self::liveMinute(180, 90);
        $goals1 = [[24, 'h', 'Mamadou Camara', 'a1'], [58, 'a', 'M. Fofana', null], [77, 'h', 'A. Soumah', null]];
        $passed = array_values(array_filter($goals1, fn ($g) => $g[0] <= $min1));
        $hs = count(array_filter($passed, fn ($g) => $g[1] === 'h'));
        $as = count($passed) - $hs;
        $events1 = array_map(fn ($g) => ['minute' => $g[0], 'side' => $g[1], 'type' => 'but', 'player' => $g[2], 'athlete_id' => $g[3]], $passed);
        if ($min1 >= 51) {
            $events1[] = ['minute' => 51, 'side' => 'a', 'type' => 'jaune', 'player' => 'I. Sylla', 'athlete_id' => null];
        }
        $mamadouGoals = count(array_filter($passed, fn ($g) => $g[3] === 'a1'));
        $shots = intdiv($min1 * 18, 90);

        $min2 = self::liveMinute(120, 40);
        $bh = 2 * $min2 + intdiv($min2, 3);
        $ba = 2 * $min2 - intdiv($min2, 5);

        $list = [
            ['id' => 'm1', 'sport' => 'Football', 'competition' => 'Championnat national U20', 'home' => 'AS Kaloum U20', 'away' => 'Horoya AC U20',
                'hs' => $hs, 'as' => $as, 'status' => 'live', 'minute' => $min1, 'date_label' => '', 'result' => null, 'events' => $events1,
                'stats' => ['Possession (%)' => [54, 46], 'Tirs' => [$shots, intdiv($shots * 2, 3)], 'Tirs cadrés' => [intdiv($shots, 2), intdiv($shots, 4)], 'Corners' => [intdiv($min1, 15), intdiv($min1, 25)]],
                'ratings' => $mamadouGoals ? [['athlete_id' => 'a1', 'note' => 7.8 + 0.6 * $mamadouGoals, 'detail' => $mamadouGoals.' but · 4 dribbles réussis']] : [],
                'candidates' => ['Mamadou Camara', 'A. Soumah', 'M. Fofana'], 'votes' => [41, 12, 9]],
            ['id' => 'm2', 'sport' => 'Basket', 'competition' => 'Championnat jeunes filles', 'home' => 'Kindia Stars', 'away' => 'SC Conakry',
                'hs' => $bh, 'as' => $ba, 'status' => 'live', 'minute' => $min2, 'date_label' => '', 'result' => null, 'events' => [],
                'stats' => ['Rebonds' => [intdiv($min2 * 29, 40), intdiv($min2 * 24, 40)], 'Passes décisives' => [intdiv($min2 * 11, 40), intdiv($min2 * 9, 40)]],
                'ratings' => [['athlete_id' => 'a2', 'note' => 8.9, 'detail' => intdiv($bh, 4).' pts · 8 rebonds']],
                'candidates' => ['Aïssatou Bah', 'N. Diallo', 'F. Camara'], 'votes' => [33, 8, 6]],
            ['id' => 'm3', 'sport' => 'Football', 'competition' => 'Championnat national U20', 'home' => 'Milo FC U20', 'away' => 'Académie Fouta FC',
                'hs' => 0, 'as' => 2, 'status' => 'finished', 'minute' => 90, 'date_label' => 'Hier', 'result' => null,
                'events' => [['minute' => 18, 'side' => 'a', 'type' => 'but', 'player' => 'D. Baldé', 'athlete_id' => null], ['minute' => 44, 'side' => 'h', 'type' => 'jaune', 'player' => 'Sékou Bangoura', 'athlete_id' => 'a9'], ['minute' => 77, 'side' => 'a', 'type' => 'but', 'player' => 'O. Bah', 'athlete_id' => null], ['minute' => 85, 'side' => 'h', 'type' => 'rouge', 'player' => 'T. Cissé', 'athlete_id' => null]],
                'stats' => ['Possession (%)' => [49, 51], 'Tirs' => [8, 10], 'Tirs cadrés' => [3, 5], 'Corners' => [4, 2]],
                'ratings' => [['athlete_id' => 'a7', 'note' => 8.7, 'detail' => 'Clean sheet · 6 arrêts'], ['athlete_id' => 'a9', 'note' => 6.8, 'detail' => '41 duels gagnés · 1 carton jaune']],
                'candidates' => ['Moussa Keita', 'O. Bah', 'D. Baldé'], 'votes' => [58, 21, 14]],
            ['id' => 'm4', 'sport' => 'Lutte', 'competition' => 'Gala de lutte de Thiès', 'home' => 'Ousmane Sow', 'away' => 'Pape Ndour', 'hs' => 0, 'as' => 0,
                'status' => 'finished', 'minute' => 0, 'date_label' => 'Sam. 17 oct.', 'result' => "Victoire d'Ousmane Sow par chute (2 min 40)", 'events' => [], 'stats' => [],
                'ratings' => [['athlete_id' => 'a4', 'note' => 9.1, 'detail' => 'Victoire par chute en 2 min 40']], 'candidates' => [], 'votes' => []],
            ['id' => 'm5', 'sport' => 'Athlétisme', 'competition' => 'Meeting national de Dakar', 'home' => '100 m hommes', 'away' => 'Finale', 'hs' => 0, 'as' => 0,
                'status' => 'finished', 'minute' => 0, 'date_label' => 'Dim. 18 oct.', 'result' => '1. Ibrahima Diallo 10"34 · 2. C. Faye 10"41 · 3. B. Sène 10"52', 'events' => [], 'stats' => [],
                'ratings' => [['athlete_id' => 'a3', 'note' => 8.6, 'detail' => '1er de la finale en 10"34']], 'candidates' => [], 'votes' => []],
            ['id' => 'm6', 'sport' => 'Handball', 'competition' => 'Coupe nationale', 'home' => 'Red Star HBC', 'away' => 'HBC Plateau', 'hs' => 31, 'as' => 27,
                'status' => 'finished', 'minute' => 60, 'date_label' => 'Sam. 17 oct.', 'result' => null, 'events' => [],
                'stats' => ['Arrêts du gardien' => [14, 11], 'Exclusions 2 min' => [3, 5]],
                'ratings' => [['athlete_id' => 'a6', 'note' => 7.9, 'detail' => '7 buts sur 10 tirs']], 'candidates' => [], 'votes' => []],
            ['id' => 'm7', 'sport' => 'Football', 'competition' => 'Championnat national U20', 'home' => 'Horoya AC U20', 'away' => 'Milo FC U20', 'hs' => 0, 'as' => 0,
                'status' => 'upcoming', 'minute' => 0, 'date_label' => 'Sam. 24 oct. · 16:00', 'result' => null, 'events' => [], 'stats' => [], 'ratings' => [],
                'candidates' => ['Sékou Bangoura', 'I. Sylla', 'M. Fofana'], 'votes' => [0, 0, 0]],
        ];

        // Saisies de l'organisateur (vue recruteur) et votes du visiteur
        $org = $this->s('org', []);
        $myVotes = $this->s('votes', []);
        foreach ($list as &$m) {
            if (isset($org[$m['id']])) {
                $m['hs'] += $org[$m['id']]['h'] ?? 0;
                $m['as'] += $org[$m['id']]['a'] ?? 0;
                $m['status'] = $org[$m['id']]['status'] ?? $m['status'];
                if ($m['status'] === 'live' && $m['minute'] === 0) {
                    $m['minute'] = 1;
                }
            }
            $m['my_vote'] = $myVotes[$m['id']] ?? null;
            if ($m['my_vote'] !== null && isset($m['votes'][$m['my_vote']])) {
                $m['votes'][$m['my_vote']]++;
            }
        }
        unset($m);

        return $list;
    }

    public function match(string $id): ?array
    {
        foreach ($this->matches() as $m) {
            if ($m['id'] === $id) {
                return $m;
            }
        }

        return null;
    }

    public function vote(string $matchId, int $index): void
    {
        $v = $this->s('votes', []);
        $v[$matchId] ??= $index;
        $this->put('votes', $v);
    }

    public function standings(): array
    {
        return Standings::compute(self::STANDINGS, $this->matches());
    }

    public function organizerScore(string $matchId, string $side, int $points): void
    {
        $org = $this->s('org', []);
        $org[$matchId][$side] = ($org[$matchId][$side] ?? 0) + $points;
        $org[$matchId]['status'] ??= 'live';
        $this->put('org', $org);
    }

    public function organizerStatus(string $matchId, string $status): void
    {
        $org = $this->s('org', []);
        $org[$matchId]['status'] = $status;
        $this->put('org', $org);
    }

    // ------------------------------------------------------------------ offres

    public function offers(): array
    {
        $applied = $this->s('applied', []);
        $me = $this->user()['athlete_id'] ?? null;
        $isRecruiter = ($this->user()['role'] ?? null) === 'recruiter';
        $base = [
            ['id' => 'o1', 'organization' => 'AS Kaloum', 'org_type' => 'Club', 'sport' => 'Football', 'title' => 'Détection U19 – ailiers et attaquants', 'place' => 'Conakry', 'deadline' => '15 nov. 2026', 'criteria' => 'Nés en 2007 ou 2008', 'verified' => true, 'mine' => $isRecruiter, 'candidates' => ['a1', 'a9']],
            ['id' => 'o2', 'organization' => "Université d'exemple (NCAA D1)", 'org_type' => 'Université', 'sport' => 'Athlétisme', 'title' => 'Bourse sportive – sprint 2027-28', 'place' => 'États-Unis', 'deadline' => '31 janv. 2027', 'criteria' => '100 m sous 10"60, bac obtenu', 'verified' => true, 'mine' => false, 'candidates' => ['a3']],
            ['id' => 'o3', 'organization' => 'Académie Fouta FC', 'org_type' => 'Académie', 'sport' => 'Football', 'title' => 'Gardiens U17 – internat', 'place' => 'Labé', 'deadline' => '20 déc. 2026', 'criteria' => 'Taille 1,80 m minimum', 'verified' => true, 'mine' => false, 'candidates' => ['a7']],
            ['id' => 'o4', 'organization' => 'Fédération de judo (exemple)', 'org_type' => 'Fédération', 'sport' => 'Judo', 'title' => 'Stage équipe nationale -57 / -63 kg', 'place' => 'Bamako', 'deadline' => '5 janv. 2027', 'criteria' => 'Ceinture noire', 'verified' => true, 'mine' => false, 'candidates' => ['a5']],
        ];
        $list = array_merge(array_map(fn ($o) => $o + ['mine' => true], $this->s('offers', [])), $base);
        foreach ($list as &$o) {
            if (in_array($o['id'], $applied, true) && $me && ! in_array($me, $o['candidates'], true)) {
                $o['candidates'][] = $me;
            }
            $o['applied'] = $me && in_array($me, $o['candidates'], true);
            $o['mine'] = $o['mine'] && $isRecruiter;
        }
        unset($o);

        return $list;
    }

    public function createOffer(array $data): void
    {
        $offers = $this->s('offers', []);
        array_unshift($offers, [
            'id' => 'o-'.Str::random(6), 'organization' => $this->user()['name'] ?? 'Mon club', 'org_type' => 'Club',
            'sport' => $data['sport'], 'title' => $data['title'], 'place' => $data['place'] ?: 'Conakry',
            'deadline' => $data['deadline'] ?: '31 déc. 2026', 'criteria' => $data['criteria'] ?: 'Ouvert à tous', 'verified' => true, 'candidates' => [],
        ]);
        $this->put('offers', $offers);
    }

    public function apply(string $offerId, ?string $videoId, string $message): void
    {
        $this->put('applied', array_values(array_unique(array_merge($this->s('applied', []), [$offerId]))));
    }

    // ------------------------------------------------------------------ messages

    public function person(string $id): array
    {
        if (isset(self::RECRUITERS[$id])) {
            return self::RECRUITERS[$id] + ['minor' => false];
        }
        $u = $this->user();
        if ($u && $id === ($u['athlete_id'] ?? $u['id'])) {
            return ['id' => $id, 'name' => $u['name'], 'sport' => '', 'verified' => (bool) $u['verified'], 'minor' => false];
        }
        if (str_starts_with($id, 'r-')) {
            return ['id' => $id, 'name' => 'Recruteur', 'sport' => '', 'verified' => false, 'minor' => false];
        }
        $a = $this->athlete($id);

        return $a ? ['id' => $id, 'name' => $a['name'], 'sport' => $a['sport'], 'verified' => $a['verified'], 'minor' => $a['age'] < 18]
            : ['id' => $id, 'name' => 'Utilisateur', 'sport' => '', 'verified' => false, 'minor' => false];
    }

    /** Recruteur Premium (contacts illimités) ? */
    public function isPremium(): bool
    {
        return (bool) $this->s('premium', false) || ! empty($this->user()['premium']);
    }

    // ------------------------------------------------------------------ compte

    public function activate(string $product): void
    {
        if ($product === 'premium') {
            $this->put('premium', true);
            $u = $this->user();
            if ($u) {
                $u['premium'] = true;
                session(['terrain.user' => $u]);
            }
        }
        if ($product === 'boost') {
            $this->put('boost', true);
        }
    }

    public function report(string $targetType, string $targetId, string $reason): void
    {
        // En démo, rien n'est envoyé.
    }

    public function boosted(): bool
    {
        return (bool) $this->s('boost', false);
    }
}
