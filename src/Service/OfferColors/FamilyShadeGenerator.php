<?php
declare(strict_types=1);

namespace App\Service\OfferColors;

/**
 * Nuances d'une teinte de famille, du plus foncé au plus clair.
 *
 * Pas de librairie couleur : la conversion HSL vers hex est locale.
 */
class FamilyShadeGenerator
{
    /**
     * Degrés HSL du catalogue, dans l'ordre d'attribution automatique.
     *
     * @var array<int, string>
     */
    public const CATALOG = [
        210 => 'Bleu',
        190 => 'Cyan',
        145 => 'Vert',
        48 => 'Jaune',
        24 => 'Orange',
        0 => 'Rouge',
        275 => 'Violet',
        330 => 'Rose',
    ];

    public const SATURATION = 0.62;

    public const MIN_LIGHTNESS = 0.32;

    public const MAX_LIGHTNESS = 0.50;

    /**
     * @return list<string> Codes `#rrggbb`, le premier est le plus foncé.
     */
    public function shades(int $hue, int $count): array
    {
        if ($count < 1) {
            return [];
        }

        $hexes = [];
        for ($index = 0; $index < $count; $index++) {
            $lightness = $count === 1
                ? (self::MIN_LIGHTNESS + self::MAX_LIGHTNESS) / 2
                : self::MIN_LIGHTNESS + (self::MAX_LIGHTNESS - self::MIN_LIGHTNESS) * $index / ($count - 1);
            $hex = $this->hslToHex($hue, self::SATURATION, $lightness);
            while (in_array($hex, $hexes, true) && $lightness < 0.9) {
                $lightness += 0.01;
                $hex = $this->hslToHex($hue, self::SATURATION, $lightness);
            }
            $hexes[] = $hex;
        }

        return $hexes;
    }

    /**
     * @return string Code `#rrggbb`
     */
    private function hslToHex(int $hue, float $saturation, float $lightness): string
    {
        $h = $hue % 360;
        if ($h < 0) {
            $h += 360;
        }

        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $x = $chroma * (1 - abs(fmod($h / 60, 2) - 1));
        $match = $lightness - $chroma / 2;

        if ($h < 60) {
            [$red, $green, $blue] = [$chroma, $x, 0.0];
        } elseif ($h < 120) {
            [$red, $green, $blue] = [$x, $chroma, 0.0];
        } elseif ($h < 180) {
            [$red, $green, $blue] = [0.0, $chroma, $x];
        } elseif ($h < 240) {
            [$red, $green, $blue] = [0.0, $x, $chroma];
        } elseif ($h < 300) {
            [$red, $green, $blue] = [$x, 0.0, $chroma];
        } else {
            [$red, $green, $blue] = [$chroma, 0.0, $x];
        }

        return sprintf(
            '#%02x%02x%02x',
            (int)round(($red + $match) * 255),
            (int)round(($green + $match) * 255),
            (int)round(($blue + $match) * 255),
        );
    }
}
