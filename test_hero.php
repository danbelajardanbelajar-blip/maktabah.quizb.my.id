<?php
$html = file_get_contents('https://maktabah.quizb.my.id/');
echo "COUNT: " . substr_count($html, 'ssr-hero-wrapper') . "\n";
