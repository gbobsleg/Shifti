<?php
declare(strict_types=1);

namespace App\Service\Authorization;

use App\Authorization\RoleCapabilities;
use Cake\ORM\TableRegistry;

/**
 * Répond « cet utilisateur a-t-il cette capacité ? » à partir du code de son rôle.
 */
class PermissionService
{
    /**
     * Rôles chargés depuis la base, partagés pour la durée de la requête.
     *
     * @var array<int, array{code: string|null, priority: int, name: string|null}>|null
     */
    private static ?array $cachedRoles = null;

    /**
     * @var array<int, array{code: string|null, priority: int, name: string|null}>
     */
    private array $roles;

    /**
     * @param array<int, array{code: string|null, priority: int, name?: string|null}>|null $roles
     */
    public function __construct(?array $roles = null)
    {
        $this->roles = $roles ?? self::rolesFromDatabase();
    }

    /**
     * Indique si l'identité possède la capacité.
     *
     * @param mixed $identity Identité connectée.
     * @param string $capability Code de capacité.
     * @return bool
     */
    public function has(mixed $identity, string $capability): bool
    {
        $roleId = $this->roleId($identity);
        $code = $this->roles[$roleId]['code'] ?? null;

        return in_array($capability, RoleCapabilities::forCode($code), true);
    }

    /**
     * Priorité du rôle de l'identité. null si le rôle est inconnu.
     *
     * @param mixed $identity Identité connectée.
     * @return int|null
     */
    public function rolePriority(mixed $identity): ?int
    {
        $roleId = $this->roleId($identity);
        if (!isset($this->roles[$roleId])) {
            return null;
        }

        return $this->roles[$roleId]['priority'];
    }

    /**
     * Rôles de priorité inférieure ou égale (nombre plus grand ou égal).
     *
     * @return list<int>
     */
    public function assignableRoleIds(mixed $identity): array
    {
        $priority = $this->rolePriority($identity);
        if ($priority === null) {
            return [];
        }

        $ids = [];
        foreach ($this->roles as $id => $role) {
            if ($role['priority'] >= $priority) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Indique si l'acteur peut modifier le compte qui porte ce rôle.
     *
     * @param mixed $identity Identité connectée.
     * @param int $targetRoleId Rôle du compte cible.
     * @return bool
     */
    public function canManageUser(mixed $identity, int $targetRoleId): bool
    {
        $actorPriority = $this->rolePriority($identity);
        $targetPriority = $this->roles[$targetRoleId]['priority'] ?? null;
        if ($actorPriority === null || $targetPriority === null) {
            return false;
        }

        return $targetPriority >= $actorPriority;
    }

    /**
     * Nom du rôle de l'identité. null si le rôle est inconnu.
     *
     * @param mixed $identity Identité connectée.
     * @return string|null
     */
    public function roleName(mixed $identity): ?string
    {
        $name = $this->roles[$this->roleId($identity)]['name'] ?? null;
        if (!is_string($name) || $name === '') {
            return null;
        }

        return $name;
    }

    /**
     * @return array<int, array{code: string|null, priority: int, name: string|null}>
     */
    private static function rolesFromDatabase(): array
    {
        if (self::$cachedRoles !== null) {
            return self::$cachedRoles;
        }

        self::$cachedRoles = [];
        $rows = TableRegistry::getTableLocator()->get('Roles')->find()
            ->select(['id', 'code', 'priority', 'name'])
            ->all();
        foreach ($rows as $row) {
            self::$cachedRoles[(int)$row->id] = [
                'code' => $row->code !== null ? (string)$row->code : null,
                'priority' => (int)$row->priority,
                'name' => $row->name !== null && (string)$row->name !== '' ? (string)$row->name : null,
            ];
        }

        return self::$cachedRoles;
    }

    /**
     * Identifiant de rôle porté par l'identité, 0 si absent.
     *
     * @param mixed $identity Identité connectée.
     * @return int
     */
    private function roleId(mixed $identity): int
    {
        if (is_object($identity) && method_exists($identity, 'get')) {
            $roleId = $identity->get('role_id');
            if ($roleId) {
                return (int)$roleId;
            }
        }

        if (is_object($identity) && method_exists($identity, 'getOriginalData')) {
            $original = $identity->getOriginalData();
            if (is_object($original) && isset($original->role_id)) {
                return (int)$original->role_id;
            }
            if (is_array($original) && isset($original['role_id'])) {
                return (int)$original['role_id'];
            }
        }

        return 0;
    }
}
