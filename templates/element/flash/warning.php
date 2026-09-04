<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="flash-toast is-warning" role="alert"<?= $this->element('flash/_dismiss_attr', ['params' => $params]) ?>>
    <?= $message ?>
    <button type="button" class="btn-close" data-flash-dismiss aria-label="Fermer"></button>
</div>
