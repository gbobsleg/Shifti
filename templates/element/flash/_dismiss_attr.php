<?php
/**
 * @var array $params
 * @var int|null $defaultDelay
 */
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

if ($autoDismissValue === null && isset($defaultDelay) && $defaultDelay !== null) {
    $autoDismissValue = $defaultDelay;
}

if ($autoDismissValue !== null && is_numeric($autoDismissValue) && (int)$autoDismissValue > 0) {
    echo ' data-auto-dismiss="' . (int)$autoDismissValue . '"';
}
