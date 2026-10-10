<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

/**
 * Identité minimale pour les tests unitaires de droits.
 */
class TestIdentity
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data)
    {
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOriginalData(): array
    {
        return $this->data;
    }
}
