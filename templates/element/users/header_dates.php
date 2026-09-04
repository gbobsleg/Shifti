<?php
/**
 * Dates de création / modification à côté du titre (view / edit).
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
$formatUserTs = static function ($value): ?string {
    if (!$value instanceof \DateTimeInterface) {
        return null;
    }
    if ((int)$value->format('Y') < 1) {
        return null;
    }
    if (method_exists($value, 'i18nFormat')) {
        return $value->i18nFormat('dd/MM/yyyy HH:mm');
    }

    return $value->format('d/m/Y H:i');
};

$created = $formatUserTs($user->created ?? null);
$modified = $formatUserTs($user->modified ?? null);
if ($created === null && $modified === null) {
    return;
}

$parts = [];
if ($created !== null) {
    $parts[] = 'Créé le ' . h($created);
}
if ($modified !== null) {
    $parts[] = 'Modifié le ' . h($modified);
}
?>
<p class="crud-header-meta"><?= implode(' · ', $parts) ?></p>
