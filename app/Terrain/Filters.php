<?php

namespace App\Terrain;

use Illuminate\Support\Str;

/** Filtres et tri de la recherche, communs au mode démo et à Supabase. */
class Filters
{
    public static function apply(array $athletes, array $f): array
    {
        $q = Str::lower(trim((string) ($f['q'] ?? '')));
        $sport = $f['sport'] ?? 'Tous';
        $country = $f['country'] ?? 'Tous';
        $age = $f['age'] ?? 'Tous';
        $verified = ! empty($f['verified']);
        $poste = (string) ($f['poste'] ?? '');
        $pied = (string) ($f['pied'] ?? '');
        $dispo = ! empty($f['dispo']);

        $out = array_values(array_filter($athletes, function ($a) use ($q, $sport, $country, $age, $verified, $poste, $pied, $dispo) {
            if ($poste !== '' && ($a['fields']['Poste'] ?? '') !== $poste) {
                return false;
            }
            if ($pied !== '' && ($a['fields']['Pied fort'] ?? '') !== $pied && ! ($pied !== 'Les deux' && ($a['fields']['Pied fort'] ?? '') === 'Les deux')) {
                return false;
            }
            if ($dispo && ! str_starts_with((string) ($a['fields']['Disponible pour des essais'] ?? ''), 'Oui')) {
                return false;
            }
            $hay = Str::lower($a['name'].' '.$a['city'].' '.$a['club'].' '.$a['country'].' '.($a['specialty'] ?? '').' '.implode(' ', $a['fields']));
            if ($q !== '' && ! str_contains($hay, $q)) {
                return false;
            }
            if ($sport && $sport !== 'Tous' && $a['sport'] !== $sport) {
                return false;
            }
            if ($country && $country !== 'Tous' && $a['country'] !== $country) {
                return false;
            }
            if ($age === '-18' && $a['age'] >= 18) {
                return false;
            }
            if ($age === '18-21' && ($a['age'] < 18 || $a['age'] > 21)) {
                return false;
            }
            if ($age === '22+' && $a['age'] < 22) {
                return false;
            }

            return ! $verified || $a['verified'];
        }));

        $views = fn ($a) => array_sum(array_column($a['videos'], 'views'));
        usort($out, match ($f['sort'] ?? 'score') {
            'vues' => fn ($x, $y) => $views($y) <=> $views($x),
            'jeune' => fn ($x, $y) => $x['age'] <=> $y['age'],
            default => fn ($x, $y) => $y['score'] <=> $x['score'],
        });

        return $out;
    }
}
