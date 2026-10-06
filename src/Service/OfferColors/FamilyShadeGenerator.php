<?php
declare(strict_types=1);

namespace App\Service\OfferColors;

/**
 * Nuances d'une teinte de famille.
 *
 * La couleur de base est au milieu de la colonne. Les offres d'avant
 * s'en écartent vers la teinte plus basse, celles d'après vers la plus haute.
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

    public const SATURATION = 0.72;

    public const SATURATION_DARK = 1.0;

    public const SATURATION_LIGHT = 0.45;

    public const WARM_HUE_MIN = 20;

    public const WARM_HUE_MAX = 70;

    public const HUE_ARC_LEFT = 25;

    public const HUE_ARC_RIGHT = 55;

    public const PAIR_LIGHTNESS = 0.32;

    public const PASTEL_SATURATION = 0.42;

    public const PASTEL_LIGHTNESS = 0.78;

    public const PASTEL_PAIR_SATURATION = 0.48;

    public const PASTEL_PAIR_LIGHTNESS = 0.62;

    public const CONTRAST_LIGHT = 2.1;

    public const LABEL_CONTRAST = 4.5;

    public const SATURATION_WARM = 1.0;

    public const CONTRAST_DARK_WARM = 5.5;

    public const CONTRAST_DARK = 10.0;

    public const LIGHTNESS_MIN = 0.12;

    public const LIGHTNESS_MAX = 0.82;

    public const CONTRAST_TOLERANCE = 0.05;

    public const MAX_ITERATIONS = 24;

    /**
     * @return list<string> Codes `#rrggbb`. L'entrée du milieu est la couleur de base.
     */
    public function shades(int $hue, int $count, bool $pastel = false): array
    {
        if ($count < 1) {
            return [];
        }

        $hue = $this->normalizeHue($hue);
        $saturation = $pastel ? self::PASTEL_SATURATION : 1.0;
        $lightness = $pastel ? self::PASTEL_LIGHTNESS : 0.5;
        if ($count === 1) {
            return [$this->hslToHex($hue, $saturation, $lightness)];
        }

        [$left, $right] = $this->arcRooms();
        // Deux offres : la teinte choisie, et la même teinte plus sombre.
        // Les bouts de l'arc enverraient le vert dans le bleu.
        if ($count === 2) {
            if ($pastel) {
                return [
                    $this->hslToHex($hue, self::PASTEL_PAIR_SATURATION, self::PASTEL_PAIR_LIGHTNESS),
                    $this->hslToHex($hue, $saturation, $lightness),
                ];
            }

            return [
                $this->hslToHex($hue, 1.0, self::PAIR_LIGHTNESS),
                $this->baseHex($hue),
            ];
        }

        $center = (int)round(($count - 1) / 2);
        $hexes = [];
        for ($index = 0; $index < $count; $index++) {
            if ($index === $center) {
                $hexes[] = $pastel
                    ? $this->hslToHex($hue, $saturation, $lightness)
                    : $this->baseHex($hue);
                continue;
            }
            if ($index < $center) {
                $offerHue = $hue - $left * ($center - $index) / $center;
            } else {
                $offerHue = $hue + $right * ($index - $center) / ($count - 1 - $center);
            }
            $hexes[] = $this->hslToHex($this->normalizeHue((int)round($offerHue)), $saturation, $lightness);
        }

        return $hexes;
    }

    /**
     * Contraste du hex contre le blanc. Sert aux tests et au script, via la même formule.
     */
    public function contrastOfHex(string $hex): float
    {
        $red = hexdec(substr($hex, 1, 2)) / 255;
        $green = hexdec(substr($hex, 3, 2)) / 255;
        $blue = hexdec(substr($hex, 5, 2)) / 255;

        return $this->contrastFromChannels($red, $green, $blue);
    }

    /**
     * Le nom sur la barre passe au noir quand le blanc n'atteint pas 4,5:1.
     */
    public function labelNeedsInk(string $hex): bool
    {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            return false;
        }

        return $this->contrastOfHex($hex) < self::LABEL_CONTRAST;
    }

    /**
     * Couleur pure de la teinte catalogue : saturation pleine, clarté 50 %.
     * C'est la pastille de la liste, et la teinte dont partent les nuances.
     */
    public function baseHex(int $hue): string
    {
        return $this->hslToHex($this->normalizeHue($hue), 1.0, 0.5);
    }

    /**
     * Teinte HSL approximée du hex, pour vérifier l'absence de décalage.
     */
    public function hueOfHex(string $hex): float
    {
        $red = hexdec(substr($hex, 1, 2)) / 255;
        $green = hexdec(substr($hex, 3, 2)) / 255;
        $blue = hexdec(substr($hex, 5, 2)) / 255;
        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $delta = $max - $min;
        if ($delta < 0.00001) {
            return 0.0;
        }
        if ($max === $red) {
            $hue = 60 * fmod(($green - $blue) / $delta, 6);
        } elseif ($max === $green) {
            $hue = 60 * (($blue - $red) / $delta + 2);
        } else {
            $hue = 60 * (($red - $green) / $delta + 4);
        }
        if ($hue < 0) {
            $hue += 360;
        }

        return $hue;
    }

    /**
     * Largeur d'arc à gauche et à droite de la teinte catalogue.
     * Même arc pour toutes les teintes, y compris le jaune et l'orange.
     *
     * @return array{0: float, 1: float}
     */
    private function arcRooms(): array
    {
        return [(float)self::HUE_ARC_LEFT, (float)self::HUE_ARC_RIGHT];
    }

    private function contrastFromChannels(float $red, float $green, float $blue): float
    {
        $luminance = 0.2126 * $this->linearize($red)
            + 0.7152 * $this->linearize($green)
            + 0.0722 * $this->linearize($blue);

        return 1.05 / ($luminance + 0.05);
    }

    private function linearize(float $channel): float
    {
        if ($channel <= 0.04045) {
            return $channel / 12.92;
        }

        return (($channel + 0.055) / 1.055) ** 2.4;
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function hslToRgb(int $hue, float $saturation, float $lightness): array
    {
        $h = $this->normalizeHue($hue);
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

        return [$red + $match, $green + $match, $blue + $match];
    }

    /**
     * @return string Code `#rrggbb`
     */
    private function hslToHex(int $hue, float $saturation, float $lightness): string
    {
        [$red, $green, $blue] = $this->hslToRgb($hue, $saturation, $lightness);

        return sprintf(
            '#%02x%02x%02x',
            (int)round($red * 255),
            (int)round($green * 255),
            (int)round($blue * 255),
        );
    }

    private function normalizeHue(int $hue): int
    {
        return ($hue % 360 + 360) % 360;
    }
}
