<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'Accès refusé');
$this->setLayout('error');

echo $this->element('error_state', [
    'icon' => 'bi-lock',
    'title' => 'Accès refusé',
    'text' => 'Tu n’as pas les droits pour cette page.',
]);
