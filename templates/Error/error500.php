<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 * @var string $url
 */
use Cake\Core\Configure;
use Cake\Error\Debugger;

$isForbiddenLike = is_string($message ?? null) && (
    stripos((string)$message, 'not authorized') !== false
    || stripos((string)$message, 'authorized to perform') !== false
);

if ($isForbiddenLike) {
    $this->assign('title', 'Accès refusé');
    $this->setLayout('error');
    echo $this->element('error_state', [
        'icon' => 'bi-lock',
        'title' => 'Accès refusé',
        'text' => 'Tu n’as pas les droits pour cette page.',
    ]);

    return;
}

if (Configure::read('debug')) {
    $this->setLayout('dev_error');
    $this->assign('title', $message);
    $this->assign('templateName', 'error500.php');
    $this->start('file');
    if (isset($error) && $error instanceof Error) {
        $file = $error->getFile();
        $line = $error->getLine();
        echo '<strong>Error in: </strong>';
        echo $this->Html->link(
            sprintf('%s, line %s', Debugger::trimPath($file), $line),
            Debugger::editorUrl($file, $line)
        );
    }
    echo $this->element('auto_table_warning');
    $this->end();

    return;
}

$this->assign('title', 'Erreur');
$this->setLayout('error');

echo $this->element('error_state', [
    'icon' => 'bi-exclamation-triangle',
    'title' => 'Un problème est survenu',
    'text' => 'Réessaie dans un instant. Si ça continue, préviens un administrateur.',
]);
