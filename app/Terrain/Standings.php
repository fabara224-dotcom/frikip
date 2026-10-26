<?php

namespace App\Terrain;

/** Classement du championnat, en comptant les matchs en cours. */
class Standings
{
    /** Classement calculé uniquement à partir des matchs terminés et en cours (mode Supabase). */
    public static function fromMatches(array $matches, string $competition = 'Championnat national U20'): array
    {
        $base = [];
        $finished = [];
        foreach ($matches as $m) {
            if ($m['competition'] !== $competition || $m['result'] !== null) {
                continue;
            }
            foreach ([$m['home'], $m['away']] as $t) {
                $base[$t] ??= [$t, 0, 0, 0, 0, 0, 0];
            }
            if ($m['status'] === 'finished') {
                $finished[] = $m;
            }
        }
        foreach ($finished as $m) {
            $h = &$base[$m['home']];
            $a = &$base[$m['away']];
            $h[1]++; $a[1]++;
            $h[5] += $m['hs']; $h[6] += $m['as'];
            $a[5] += $m['as']; $a[6] += $m['hs'];
            if ($m['hs'] > $m['as']) { $h[2]++; $a[4]++; } elseif ($m['hs'] < $m['as']) { $a[2]++; $h[4]++; } else { $h[3]++; $a[3]++; }
            unset($h, $a);
        }

        return self::compute(array_values($base), $matches, $competition);
    }

    /**
     * @param  array  $base  lignes [équipe, joués, gagnés, nuls, perdus, buts pour, buts contre]
     * @param  array  $matches  matchs au format DataSource
     */
    public static function compute(array $base, array $matches, string $competition = 'Championnat national U20'): array
    {
        $rows = [];
        foreach ($base as [$team, $p, $w, $d, $l, $gf, $ga]) {
            $rows[$team] = compact('team', 'p', 'w', 'd', 'l', 'gf', 'ga') + ['live' => false];
        }
        foreach ($matches as $m) {
            if ($m['status'] !== 'live' || $m['competition'] !== $competition || ! isset($rows[$m['home']], $rows[$m['away']])) {
                continue;
            }
            $h = &$rows[$m['home']];
            $a = &$rows[$m['away']];
            $h['live'] = $a['live'] = true;
            $h['p']++;
            $a['p']++;
            $h['gf'] += $m['hs'];
            $h['ga'] += $m['as'];
            $a['gf'] += $m['as'];
            $a['ga'] += $m['hs'];
            if ($m['hs'] > $m['as']) {
                $h['w']++;
                $a['l']++;
            } elseif ($m['hs'] < $m['as']) {
                $a['w']++;
                $h['l']++;
            } else {
                $h['d']++;
                $a['d']++;
            }
            unset($h, $a);
        }
        $rows = array_values(array_map(fn ($r) => $r + ['pts' => $r['w'] * 3 + $r['d'], 'diff' => $r['gf'] - $r['ga']], $rows));
        usort($rows, fn ($x, $y) => [$y['pts'], $y['diff']] <=> [$x['pts'], $x['diff']]);

        return $rows;
    }
}
