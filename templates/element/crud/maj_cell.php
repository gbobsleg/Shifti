<?php
/**
 * Colonne unique « Maj » : modified, sinon created.
 * Tooltip : créé + modifié seulement s’ils diffèrent.
 *
 * @var \App\View\AppView $this
 * @var object $entity
 */
use Cake\I18n\FrozenTime;

$modified = $entity->modified ?? null;
$created = $entity->created ?? null;
$shown = $modified ?: $created;
if (!$shown instanceof DateTimeInterface) {
    return;
}

$now = new FrozenTime();
$diff = (int)$now->diffInDays($shown);
if ($diff === 0) {
    $label = "Aujourd'hui";
} elseif ($diff === 1) {
    $label = 'Hier';
} elseif ($diff < 7) {
    $label = 'Il y a ' . $diff . ' jours';
} elseif ($diff < 30) {
    $weeks = (int)floor($diff / 7);
    $label = 'Il y a ' . $weeks . ' semaine' . ($weeks > 1 ? 's' : '');
} elseif ($diff < 365) {
    $months = (int)floor($diff / 30);
    $label = 'Il y a ' . $months . ' mois';
} else {
    $years = (int)floor($diff / 365);
    $label = 'Il y a ' . $years . ' an' . ($years > 1 ? 's' : '');
}

$fmt = static function (DateTimeInterface $date): string {
    if (method_exists($date, 'i18nFormat')) {
        return (string)$date->i18nFormat('dd/MM/yyyy HH:mm');
    }

    return $date->format('d/m/Y H:i');
};

$same = $created instanceof DateTimeInterface
    && $modified instanceof DateTimeInterface
    && $created->getTimestamp() === $modified->getTimestamp();

if ($created instanceof DateTimeInterface && $modified instanceof DateTimeInterface && !$same) {
    $title = 'Créé : ' . $fmt($created) . ' · Modifié : ' . $fmt($modified);
} else {
    $title = $fmt($shown);
}
?>
<span data-bs-toggle="tooltip" title="<?= h($title) ?>"><?= h($label) ?></span>
