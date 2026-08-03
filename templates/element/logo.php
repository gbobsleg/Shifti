<?php
declare(strict_types=1);

$logoPath = WWW_ROOT . 'img' . DS . 'logo.svg';
$svgContent = @file_get_contents($logoPath);

if ($svgContent === false) {
    return;
}

$svgContent = preg_replace('/^<\?xml.*?\?>\s*/', '', (string)$svgContent);

$additionalClass = trim((string)($class ?? ''));
if ($additionalClass !== '') {
    if (preg_match('/<svg\b[^>]*class="/i', $svgContent)) {
        $svgContent = preg_replace(
            '/(<svg\b[^>]*class=")([^"]*)(")/i',
            '$1$2 ' . h($additionalClass) . '$3',
            $svgContent,
            1
        );
    } else {
        $svgContent = preg_replace(
            '/<svg\b/i',
            '<svg class="' . h($additionalClass) . '"',
            $svgContent,
            1
        );
    }
}

echo $svgContent;

