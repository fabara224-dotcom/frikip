<?php

namespace App\Terrain\Growth;

/**
 * Lien unique du partenaire de diffusion (Admin > Diffuseurs > « Lien unique pour tous les matchs ») :
 * le partenaire donne un seul lien (sa chaîne, son lecteur ou sa page de directs) et Frikip le propose
 * sur tous les matchs, sur la page « En direct » et en haut du fil.
 *  - Accord écrit signé : la vidéo se regarde dans Frikip (lecteur intégré).
 *  - Sans accord : bouton qui ouvre le site du partenaire.
 */
class PartnerLink
{
    public function __construct(private Algorithm $algo)
    {
    }

    /** @return object{name: string, url: string, inside: bool, player: array}|null */
    public function get(): ?object
    {
        $url = trim((string) $this->algo->get('partner_url'));
        if (! $this->algo->get('partner_on') || ! self::valid($url)) {
            return null;
        }
        $inside = (bool) $this->algo->get('partner_contract');
        $player = ['type' => 'link', 'src' => $url];
        if ($inside) {
            $player = Live::embed($url);
            if ($player['type'] === 'link') {
                $player = ['type' => 'iframe', 'src' => $url];   // page lecteur du partenaire : intégrée telle quelle (accord signé)
            }
        }

        return (object) [
            'name' => (string) ($this->algo->get('partner_name') ?: __('Partenaire officiel')),
            'url' => $url,
            'inside' => $inside,
            'player' => $player,
            'note' => (string) $this->algo->get('partner_note'),
        ];
    }

    public static function valid(string $url): bool
    {
        return str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
