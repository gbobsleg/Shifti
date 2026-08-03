<?php
/**
 * Element: paginator
 * Affiche des contrôles de pagination compatibles Bootstrap.
 * Utilisation: <?= $this->element('paginator') ?>
 */
?>
<?php if ($this->Paginator->params()['pageCount'] > 1): ?>
<nav aria-label="Pagination">
    <ul class="pagination justify-content-center">
        <li class="page-item">
            <?= $this->Paginator->first('&laquo;', ['escape' => false, 'class' => 'page-link', 'templates' => ['number' => '<a class="page-link" href="{{url}}">{{text}}</a>']]) ?>
        </li>
        <li class="page-item">
            <?= $this->Paginator->prev('&lsaquo;', ['escape' => false, 'class' => 'page-link', 'templates' => ['number' => '<a class="page-link" href="{{url}}">{{text}}</a>']]) ?>
        </li>
        <?= $this->Paginator->numbers(['modulus' => 2, 'separator' => '', 'templates' => [
            'number' => '<li class="page-item"><a class="page-link" href="{{url}}">{{text}}</a></li>',
            'current' => '<li class="page-item active"><a class="page-link" href="#">{{text}}</a></li>',
        ]]) ?>
        <li class="page-item">
            <?= $this->Paginator->next('&rsaquo;', ['escape' => false, 'class' => 'page-link', 'templates' => ['number' => '<a class="page-link" href="{{url}}">{{text}}</a>']]) ?>
        </li>
        <li class="page-item">
            <?= $this->Paginator->last('&raquo;', ['escape' => false, 'class' => 'page-link', 'templates' => ['number' => '<a class="page-link" href="{{url}}">{{text}}</a>']]) ?>
        </li>
    </ul>
    <p class="text-center text-muted small mb-0">
        <?= $this->Paginator->counter(__('Page {{page}} sur {{pages}} — {{current}} éléments sur {{count}}')) ?>
    </p>
 </nav>
<?php endif; ?>



