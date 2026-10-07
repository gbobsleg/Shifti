<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Provenance d'une plage. Fermée : une valeur nouvelle passe par une migration ENUM.
 */
final class RangeSource
{
    public const MANUAL = 'manual';
    public const IMPORT = 'import';
    public const AUTO_TAD = 'auto_tad';
    public const PLANNING = 'planning';

    public const AUTO_TAD_PREFIX = '[AUTO-TAD]';
    public const IMPORT_SUFFIX = ' - GroomRH';
    public const PLANNING_WFM_PREFIX = 'Généré par WFM';
    public const PLANNING_PUBLISH_PREFIX = 'Publié depuis brouillon';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::MANUAL, self::IMPORT, self::AUTO_TAD, self::PLANNING];
    }

    /**
     * Indique si la valeur appartient à l'ENUM.
     */
    public static function isValid(string $source): bool
    {
        return in_array($source, self::values(), true);
    }

    /**
     * Libellé affiché sur les listes et les fiches.
     */
    public static function label(string $source): string
    {
        return match ($source) {
            self::MANUAL => 'Saisie manuelle',
            self::IMPORT => 'Import',
            self::AUTO_TAD => 'Télétravail automatique',
            self::PLANNING => 'Planning généré',
            default => $source,
        };
    }

    /**
     * Nom affiché du créateur. Vide + télétravail automatique = Automatique.
     */
    public static function creatorLabel(?string $name, string $source): string
    {
        $name = trim((string)$name);
        if ($name !== '') {
            return $name;
        }

        return $source === self::AUTO_TAD ? 'Automatique' : 'Inconnu';
    }

    /**
     * Reprise des plages et des snapshots d'historique d'avant la colonne source.
     */
    public static function fromLegacyComment(?string $comment): string
    {
        $comment = $comment ?? '';
        if (str_starts_with($comment, self::AUTO_TAD_PREFIX)) {
            return self::AUTO_TAD;
        }
        if (str_ends_with($comment, self::IMPORT_SUFFIX)) {
            return self::IMPORT;
        }
        if (
            str_starts_with($comment, self::PLANNING_WFM_PREFIX)
            || str_starts_with($comment, self::PLANNING_PUBLISH_PREFIX)
        ) {
            return self::PLANNING;
        }

        return self::MANUAL;
    }
}
