<?php

namespace App\Terrain\Growth;

use App\Terrain\SportFields;

/**
 * Image PNG de la carte de joueur (1080 × 1350), utilisée comme aperçu quand le lien est partagé
 * sur WhatsApp ou Facebook. Nécessite l'extension GD de PHP (présente dans Laravel Herd).
 * Police : DejaVu Sans Condensed (licence libre, dossier resources/fonts).
 */
class CardImage
{
    public static function render(array $a, float $rating, string $url): ?string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) {
            return null;
        }
        $bold = resource_path('fonts/DejaVuSansCondensed-Bold.ttf');
        $reg = resource_path('fonts/DejaVuSansCondensed.ttf');
        if (! is_file($bold)) {
            return null;
        }
        $W = 1080;
        $H = 1350;
        $im = imagecreatetruecolor($W, $H);
        [$c1, $c2] = SportFields::color($a['sport']);
        $rgb = fn (string $hex) => sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        [$r1, $g1, $b1] = $rgb($c1);
        [$r2, $g2, $b2] = $rgb($c2);
        for ($y = 0; $y < $H; $y++) {
            $t = $y / $H;
            imageline($im, 0, $y, $W, $y, imagecolorallocate($im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }
        // Image de la première compilation (si elle est sur le serveur)
        $poster = $a['videos'][0]['poster'] ?? null;
        $local = $poster ? public_path(ltrim((string) parse_url($poster, PHP_URL_PATH), '/')) : null;
        if ($local && is_file($local) && ($src = @imagecreatefromjpeg($local))) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $th = 760;
            $tw = (int) ($sw * $th / $sh);
            imagecopyresampled($im, $src, (int) (($W - $tw) / 2), 150, 0, 0, $tw, $th, $sw, $sh);
            imagedestroy($src);
            // fondu vers le bas
            for ($i = 0; $i < 260; $i++) {
                $alpha = (int) (127 - $i / 260 * 127);
                imagefilledrectangle($im, 0, 650 + $i, $W, 650 + $i, imagecolorallocatealpha($im, $r2, $g2, $b2, max(0, $alpha)));
            }
            imagefilledrectangle($im, 0, 910, $W, $H, imagecolorallocate($im, $r2, $g2, $b2));
        }
        $white = imagecolorallocate($im, 255, 255, 255);
        $soft = imagecolorallocatealpha($im, 255, 255, 255, 50);
        $gold = imagecolorallocate($im, 255, 205, 80);
        $text = function (string $s, int $size, int $x, int $y, $color, string $font) use ($im) {
            imagettftext($im, $size, 0, $x, $y, $color, $font, $s);
        };
        $center = function (string $s, int $size, int $y, $color, string $font) use ($im, $W, $text) {
            $box = imagettfbbox($size, 0, $font, $s);
            $text($s, $size, (int) (($W - ($box[2] - $box[0])) / 2), $y, $color, $font);
        };
        // En-tête : note et sport
        $text((string) round($rating), 96, 60, 150, $gold, $bold);
        $text(mb_strtoupper($a['sport']), 30, 64, 200, $white, $bold);
        $text(mb_strtoupper(config('terrain.app_name')), 30, $W - 260, 90, $soft, $bold);
        // Nom, poste
        $name = mb_strtoupper($a['name']);
        $size = mb_strlen($name) > 18 ? 58 : 72;
        $center($name, $size, 1010, $white, $bold);
        $first = (string) (array_values($a['fields'])[0] ?? $a['sport']);
        $center(mb_strtoupper($first).' · '.$a['age'].' ANS · '.mb_strtoupper($a['city']), 30, 1065, $soft, $reg);
        // Raccourcit un texte pour qu'il tienne dans la largeur voulue
        $fit = function (string $s, int $size, string $font, int $max): string {
            while (mb_strlen($s) > 1) {
                $box = imagettfbbox($size, 0, $font, $s);
                if ($box[2] - $box[0] <= $max) {
                    return $s;
                }
                $s = rtrim(mb_substr($s, 0, mb_strlen($s) - 2)).'…';
            }

            return $s;
        };
        // 3 statistiques
        $stats = array_slice($a['fields'], 1, 3, true);
        $i = 0;
        foreach ($stats as $label => $value) {
            $x = 70 + $i * 330;
            imagefilledrectangle($im, $x, 1110, $x + 300, 1240, imagecolorallocatealpha($im, 0, 0, 0, 90));
            $text($fit((string) $value, 30, $bold, 262), 30, $x + 20, 1170, $white, $bold);
            $text($fit(mb_strtoupper((string) $label), 17, $reg, 262), 17, $x + 20, 1215, $soft, $reg);
            $i++;
        }
        if (! empty($a['verified'])) {
            $text('✓ PROFIL VÉRIFIÉ', 26, 64, 250, $white, $bold);
        }
        $center(preg_replace('#^https?://#', '', $url), 26, 1305, $soft, $reg);
        ob_start();
        imagepng($im, null, 6);
        imagedestroy($im);

        return (string) ob_get_clean();
    }
}
