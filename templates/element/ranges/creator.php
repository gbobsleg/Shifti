<?php
/**
 * @var \App\Model\Entity\Range $range
 */
$creator = $range->created_by_user ?? null;
$name = '';
if ($creator) {
    $name = trim((string)$creator->first_name . ' ' . (string)$creator->last_name);
}
echo \App\Service\RangeSource::creatorLabel($name, (string)$range->source);
