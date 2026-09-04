<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
$class = 'flash-toast is-info';
if (!empty($params['class'])) {
    $class .= ' ' . $params['class'];
}
?>
<div class="<?= h($class) ?>" role="alert"<?= $this->element('flash/_dismiss_attr', ['params' => $params, 'defaultDelay' => 5000]) ?>>
    <?= $message ?>
    <button type="button" class="btn-close" data-flash-dismiss aria-label="Fermer"></button>
</div>
