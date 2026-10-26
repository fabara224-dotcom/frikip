<?php

namespace App\Console\Commands;

use App\Terrain\RealScores;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Vérifie la connexion à API-Football : clé, offre, quota du jour, compétitions et détail d'un match.
 * Utilisation : php artisan terrain:diagnostic
 */
class TerrainDiagnostic extends Command
{
    protected $signature = 'terrain:diagnostic {--vider-cache : Efface les scores en cache avant le test}';

    protected $description = 'Vérifie la connexion à API-Football (clé, quota, compétitions, détail des matchs)';

    public function handle(RealScores $real): int
    {
        $key = (string) config('terrain.api_football.key');
        if ($key === '') {
            $this->error('Aucune clé : ajoute API_FOOTBALL_KEY=... dans .env puis lance php artisan config:clear');

            return self::FAILURE;
        }
        $this->info('Clé trouvée : '.substr($key, 0, 4).'…'.substr($key, -4));

        if ($this->option('vider-cache')) {
            Cache::flush();
            $this->line('Cache vidé.');
        }

        // 1. Compte et quota (cet appel ne compte pas dans le quota chez API-Football)
        try {
            $r = Http::withHeaders(['x-apisports-key' => $key])->acceptJson()->timeout(15)->get('https://v3.football.api-sports.io/status');
            $errors = $r->json('errors');
            if (! empty($errors)) {
                $this->error('Le fournisseur refuse la clé : '.json_encode($errors, JSON_UNESCAPED_UNICODE));

                return self::FAILURE;
            }
            $st = $r->json('response');
            $this->table(['Offre', 'Active', 'Fin', 'Appels aujourd\'hui', 'Limite par jour'], [[
                $st['subscription']['plan'] ?? '?', ($st['subscription']['active'] ?? false) ? 'oui' : 'non',
                $st['subscription']['end'] ?? '?', $st['requests']['current'] ?? '?', $st['requests']['limit_day'] ?? '?',
            ]]);
            if (($st['requests']['current'] ?? 0) >= ($st['requests']['limit_day'] ?? PHP_INT_MAX)) {
                $this->warn('Quota du jour épuisé : les scores restent bloqués sur les dernières valeurs reçues jusqu\'à demain (ou passe à une offre payante).');
            }
        } catch (Throwable $e) {
            $this->error('Impossible de joindre API-Football : '.$e->getMessage());

            return self::FAILURE;
        }

        // 2. Compétitions et couverture
        $comps = $real->competitions();
        if (! $comps) {
            $this->error('Aucune compétition reçue. Erreur : '.($real->lastError() ?? 'inconnue'));

            return self::FAILURE;
        }
        $this->table(['N°', 'Compétition', 'Saison', 'Buts/cartons', 'Compositions', 'Statistiques'], array_map(fn ($c) => [
            $c['id'], $c['name'], $c['season'],
            ($c['coverage']['events'] ?? false) ? 'oui' : 'non',
            ($c['coverage']['lineups'] ?? false) ? 'oui' : 'non',
            ($c['coverage']['statistics'] ?? false) ? 'oui' : 'non',
        ], $comps));

        // 3. Matchs du jour et détail d'un match
        $today = $real->today();
        $live = array_filter($today, fn ($m) => $m['status'] === 'live');
        $this->info(count($today).' match(s) aujourd\'hui, dont '.count($live).' en direct.');

        $sample = collect($today)->firstWhere('status', 'live') ?? collect($today)->firstWhere('status', 'finished');
        if (! $sample) {
            $first = array_key_first($comps);
            $league = $real->league($first);
            $sample = $league['results'][0] ?? null;
        }
        if ($sample) {
            $m = $real->match($sample['id']);
            $this->line('');
            $this->info("Exemple : {$m['home']} {$m['hs']}-{$m['as']} {$m['away']} ({$m['competition']}, {$m['date_label']})");
            $this->line('  Buts et cartons : '.count($m['events']).' · Statistiques : '.count($m['stats']).' · Compositions : '.count($m['lineups'] ?? []));
            if (! empty($m['detail_note'])) {
                $this->warn('  '.$m['detail_note']);
            }
        }

        if ($err = $real->lastError()) {
            $this->warn('Dernière erreur du fournisseur : '.$err);
        }
        $this->info('Diagnostic terminé.');

        return self::SUCCESS;
    }
}
