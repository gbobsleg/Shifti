<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
$class = 'alert alert-info alert-dismissible fade show';
if (!empty($params['class'])) {
    // Permet de passer une classe personnalisée si besoin
    $class = $params['class'];
}
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}

// Gestion de l'auto-dismiss
$autoDismiss = '';
// Vérifier si le paramètre existe (directement ou dans params, avec tiret ou underscore)
$autoDismissValue = null;
if (isset($params['auto-dismiss'])) {
    $autoDismissValue = $params['auto-dismiss'];
} elseif (isset($params['auto_dismiss'])) {
    $autoDismissValue = $params['auto_dismiss'];
} elseif (isset($params['params']['auto-dismiss'])) {
    $autoDismissValue = $params['params']['auto-dismiss'];
} elseif (isset($params['params']['auto_dismiss'])) {
    $autoDismissValue = $params['params']['auto_dismiss'];
}

if ($autoDismissValue !== null && is_numeric($autoDismissValue) && $autoDismissValue > 0) {
    $autoDismiss = ' data-auto-dismiss="' . (int)$autoDismissValue . '"';
}
?>
<div class="<?= h($class) ?>" role="alert"<?= $autoDismiss ?>>
    <?= $message ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
