<?php
$lines = file('quic1934_maktabah.sql');
$matches_found = [];
$count = 0;

foreach($lines as $line) {
    if (strpos($line, 'INSERT INTO `book_content`') === false) {
        continue;
    }
    
    // We are looking for text inside the SQL dump values.
    // Let's use a regex to find common Arabic heading starters.
    // The text might have literal \n or \r\n
    if (preg_match_all('/(?:\\\\r\\\\n|\\\\n|\s)(باب\s[^\\\\]{5,60}|فصل\s[^\\\\]{5,60}|كتاب\s[^\\\\]{5,60}|مقدمة[^\\\\]{0,60})/u', $line, $matches)) {
        foreach($matches[1] as $m) {
            $cleaned = trim($m);
            // Ignore if it contains common paragraph punctuation
            if (strpos($cleaned, '،') === false && strpos($cleaned, '.') === false) {
                $matches_found[] = $cleaned;
                $count++;
            }
        }
    }
}

// Group by type
$grouped = [
    'كتاب' => [],
    'باب' => [],
    'فصل' => [],
    'مقدمة' => []
];

foreach ($matches_found as $m) {
    if (mb_strpos($m, 'كتاب') === 0) $grouped['كتاب'][] = $m;
    elseif (mb_strpos($m, 'باب') === 0) $grouped['باب'][] = $m;
    elseif (mb_strpos($m, 'فصل') === 0) $grouped['فصل'][] = $m;
    elseif (mb_strpos($m, 'مقدمة') === 0) $grouped['مقدمة'][] = $m;
}

echo "Total potential headings found: $count\n\n";

foreach ($grouped as $type => $list) {
    echo "--- Type: $type (" . count($list) . " found) ---\n";
    $unique = array_unique($list);
    $sample = array_slice($unique, 0, 15);
    foreach ($sample as $s) {
        echo "- $s\n";
    }
    echo "\n";
}
