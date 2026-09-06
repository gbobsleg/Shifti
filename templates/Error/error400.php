<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'Page introuvable');
$this->setLayout('error');

echo $this->element('error_state', [
    'icon' => 'bi-search',
    'title' => 'Cette page n’existe pas',
    'text' => 'L’adresse a changé ou n’est plus disponible.',
]);
