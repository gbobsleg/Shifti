<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;

/**
 * En-tête d'authentification envoyé aux services Python (solveur, Prophet).
 */
final class PythonServiceAuth
{
    public const HEADER = 'X-Shifti-Token';

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        $token = (string)Configure::read('PythonServices.token', '');

        return $token === '' ? [] : [self::HEADER => $token];
    }
}
