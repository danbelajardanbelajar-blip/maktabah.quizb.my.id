<?php
$apiKey = 'AIzaSyDDeLIfq3Vi4T3xwjQD9FJb_hRN2go7QpU';
$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey;
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
foreach ($data['models'] as $m) {
    if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [])) {
        echo $m['name'] . "\n";
    }
}
