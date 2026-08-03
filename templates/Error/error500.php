<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 * @var string $url
 */
use Cake\Core\Configure;
use Cake\Error\Debugger;

$this->setLayout('error');

// Message explicite si l'erreur correspond à un refus d'autorisation
$isForbiddenLike = is_string($message ?? null) && (
    stripos((string)$message, 'not authorized') !== false
    || stripos((string)$message, 'authorized to perform') !== false
);

if ($isForbiddenLike) :
    $this->assign('title', 'Accès refusé');
?>
<h3 class="mb-3">Accès refusé</h3>
<p class="text-muted mb-0">Vous n'êtes pas autorisé à consulter cette page.</p>
<?php
    return;
endif;

if (Configure::read('debug')) :
    $this->setLayout('dev_error');
    $this->assign('title', $message);
    $this->assign('templateName', 'error500.php');
    $this->start('file');
?>
<?php if (isset($error) && $error instanceof Error) : ?>
    <?php $file = $error->getFile() ?>
    <?php $line = $error->getLine() ?>
    <strong>Error in: </strong>
    <?= $this->Html->link(sprintf('%s, line %s', Debugger::trimPath($file), $line), Debugger::editorUrl($file, $line)); ?>
<?php endif; ?>
<?php
    echo $this->element('auto_table_warning');
    $this->end();
endif;
?>
<h2><?= __d('cake', 'An Internal Error Has Occurred.') ?></h2>
<p class="error">
    <strong><?= __d('cake', 'Error') ?>: </strong>
    <?= h($message) ?>
</p>
