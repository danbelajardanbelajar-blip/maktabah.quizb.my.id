<?php
$apiKey = 'AIzaSyDDeLIfq3Vi4T3xwjQD9FJb_hRN2go7QpU';
$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';

$prompt = "Anda adalah asisten virtual (AI) Islami bernama 'Maktabah Bot' yang ramah dan berilmu. Tugas Anda adalah menjawab pertanyaan pengguna HANYA berdasarkan referensi konteks teks dari kitab/buku yang diberikan di bawah ini. Jika jawaban tidak terdapat di dalam konteks, katakan bahwa Anda tidak menemukan informasinya di database perpustakaan ini.\n\n"
        . "--- KONTEKS KITAB ---\n"
        . "[Referensi 1: KUMPULAN BM Vol 2 Cak ZEN,Creatife (Juz 2, Hlm 30)]\nt, imam ibnu hajar berkata: atau kekhususan itulah menjadi alasan / sebab. Hukum mengerjakan proses bayi tabung Pertanyaan Bagaimana hukumnya mengerjakan proses bayi tabung. Bayi tabung ialah bayi yang dihasilkan bukan d...\n"
        . "\n---------------------\n\n"
        . "Pertanyaan Pengguna: bagaimana hukum proses bayi tabung?";

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.2, // rendah agar tidak mudah halusinasi
        "maxOutputTokens" => 1024
    ]
];
$ch = curl_init($endpoint . '?key=' . $apiKey);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$response = curl_exec($ch);
curl_close($ch);
echo "Response: $response\n";
