<?php
require 'app/Config/Database.php';
require 'app/Helpers/SearchHelper.php';

$qRaw = "ما حكم صلاة التهجد؟";
$qClean = \App\Helpers\SearchHelper::ftEscape($qRaw);
$qWords = array_filter(explode(' ', $qClean), function($w) { return mb_strlen($w) > 2; });
if (empty($qWords)) {
    $qBool = $qClean; 
} else {
    $qBool = implode('* ', $qWords) . '*';
}
echo "Clean: $qClean\n";
echo "Words: " . print_r($qWords, true) . "\n";
echo "Bool: $qBool\n";
