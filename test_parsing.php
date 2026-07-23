<?php
require 'app/Helpers/SearchHelper.php';

$qRaw = "التهجد";
$qClean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $qRaw);
$qWords = array_filter(explode(' ', $qClean), function($w) { return mb_strlen($w) > 2; });
if (empty($qWords)) {
    $qBool = \App\Helpers\SearchHelper::ftEscape($qRaw);
} else {
    $qWords = array_slice(array_values($qWords), 0, 4);
    $qBool = '+' . implode('* +', $qWords) . '*';
}
echo "qBool is: " . $qBool;
