<?php

namespace App\Services;

class AIService {
    private $apiKeys = [];
    private $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';

    public function __construct() {
        $envKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
        $this->apiKeys = array_filter(array_map('trim', explode(',', $envKey)));
    }

    public function askGemini(string $question, array $contextData): ?string {
        if (empty($this->apiKeys)) {
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
                "maxOutputTokens" => 8192
            ]
        ];

        // Acak urutan API Key untuk mendistribusikan beban (Load Balancing sederhana)
        $keysToTry = $this->apiKeys;
        shuffle($keysToTry);
        $lastError = "";

        foreach ($keysToTry as $key) {
            $ch = curl_init($this->endpoint . '?key=' . $key);
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

            if ($httpCode === 429 || $httpCode >= 500) {
                $lastError = "Server AI sedang sibuk atau mengalami gangguan (Error $httpCode). Silakan coba beberapa saat lagi.";
                continue; // Coba API Key berikutnya
            }

            if ($httpCode !== 200 || !$response) {
                return "Error: Gagal menghubungi server AI (Code: $httpCode). Detail: $curlError";
            }

            // Jika sukses (tidak 429 dan tidak error lain), keluar dari loop dan proses response
            break;
        }

        if (isset($httpCode) && ($httpCode === 429 || $httpCode >= 500)) {
            return "Error: " . $lastError;
        }

        $data = json_decode($response, true);
        
        // --- TEMPORARY LOGGING ---
        file_put_contents(__DIR__ . '/../../gemini_debug.txt', "Time: " . date('Y-m-d H:i:s') . "\nResponse:\n" . $response . "\n\n", FILE_APPEND);
        // -------------------------

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = "";
            foreach ($data['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                }
            }
            return $text;
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
