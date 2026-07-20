<?php

namespace App\Services;

class AIService {
    private $apiKey;
    private $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';

    public function __construct() {
        $this->apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
    }

    public function askGemini(string $question, array $contextData): ?string {
        if (empty($this->apiKey)) {
            return "Error: API Key Gemini belum diatur.";
        }

        // Susun prompt
        $contextText = $this->buildContextText($contextData);
        $prompt = "Anda adalah asisten virtual (AI) Islami bernama 'Maktabah Bot' yang ramah dan berilmu. Tugas Anda adalah menjawab pertanyaan pengguna HANYA berdasarkan referensi konteks teks dari kitab/buku yang diberikan di bawah ini. Jika jawaban tidak terdapat di dalam konteks, katakan bahwa Anda tidak menemukan informasinya di database perpustakaan ini.\n\n"
                . "--- KONTEKS KITAB ---\n"
                . $contextText
                . "\n---------------------\n\n"
                . "Pertanyaan Pengguna: " . $question;

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

        $ch = curl_init($this->endpoint . '?key=' . $this->apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 45); // Tambah timeout agar tidak mudah putus
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass SSL issues in shared hosting

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return "Error: Gagal menghubungi server AI (Code: $httpCode). Detail: $curlError";
        }

        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        return "Maaf, terjadi kesalahan saat memproses jawaban dari AI.";
    }

    private function buildContextText(array $contextData): string {
        if (empty($contextData)) {
            return "Tidak ada teks referensi yang ditemukan.";
        }
        
        $text = "";
        $counter = 1;
        foreach ($contextData as $row) {
            $title = $row['title'] ?? 'Kitab Tanpa Judul';
            $juz = $row['match_juz'] ?? '-';
            $page = $row['match_page'] ?? '-';
            $snippet = strip_tags($row['snippet'] ?? '');
            
            $text .= "[Referensi $counter: $title (Juz $juz, Hlm $page)]\n";
            $text .= "$snippet\n\n";
            $counter++;
        }
        return $text;
    }
}
