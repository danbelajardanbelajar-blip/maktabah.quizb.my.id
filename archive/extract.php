<?php
$content = file_get_contents('quic1934_maktabah.sql');

// Extract all strings that come after \r\n, \n, or \r and start with باب or فصل or كتاب
if (preg_match_all('/(?:\\\\r\\\\n|\\\\n|\\\\r)(باب\s[^\\\\]{3,60}|فصل\s[^\\\\]{3,60}|كتاب\s[^\\\\]{3,60})/u', $content, $matches)) {
    $found = array_unique($matches[1]);
    
    $out = "Found " . count($found) . " traditional headings:\n\n";
    $sample = array_slice($found, 0, 100);
    foreach($sample as $s) {
        $out .= "- " . trim($s) . "\n";
    }
    file_put_contents('results.txt', $out);
} else {
    file_put_contents('results.txt', 'No traditional headings found.');
}
