<?php
declare(strict_types=1);

namespace App\Test\TestCase\Authorization;

use ArrayAccess;
use Authorization\IdentityInterface;
use Authorization\Policy\Result;
use Authorization\Policy\ResultInterface;

/**
 * Identité minimale pour les tests unitaires de droits.
 *
 * @implements ArrayAccess<string, mixed>
 */
class TestIdentity implements IdentityInterface
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

    public function can(string $action, mixed $resource): bool
    {
        return false;
    }

    public function canResult(string $action, mixed $resource): ResultInterface
    {
        return new Result(false);
    }

    public function applyScope(string $action, mixed $resource, mixed ...$optionalArgs): mixed
    {
        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOriginalData(): array
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}
