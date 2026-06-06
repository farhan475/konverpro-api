<?php

namespace App\Services;

use App\Models\PengaturanGlobal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SumopodService
{
    /**
     * @param string $mkAsal
     * @param array<int, mixed> $kurikulumOptions
     * @return array<string, mixed>|null
     */
    public function getAiMatch(string $mkAsal, array $kurikulumOptions): ?array
    {
        $apiKeyRaw = PengaturanGlobal::where('setting_key', 'sumopod_api_key')->value('setting_value');
        $baseUrlRaw = PengaturanGlobal::where('setting_key', 'sumopod_base_url')->value('setting_value');
        $modelRaw = PengaturanGlobal::where('setting_key', 'sumopod_model')->value('setting_value');

        $apiKey = is_string($apiKeyRaw) ? $apiKeyRaw : '';
        $baseUrl = is_string($baseUrlRaw) ? $baseUrlRaw : 'https://api.openai.com/v1';
        $model = is_string($modelRaw) ? $modelRaw : 'gpt-4o-mini';

        if (empty($apiKey)) {
            Log::warning('Sumopod API Key not set.');
            return null;
        }

        $kurikulumList = collect($kurikulumOptions)->map(function (mixed $mk) {
            if (is_array($mk)) {
                $id = isset($mk['id']) ? strval($mk['id']) : '';
                $nama = isset($mk['nama_mk']) ? strval($mk['nama_mk']) : '';
                $sks = isset($mk['sks']) ? strval($mk['sks']) : '';
            } elseif (is_object($mk)) {
                $id = isset($mk->id) ? strval($mk->id) : '';
                $nama = isset($mk->nama_mk) ? strval($mk->nama_mk) : '';
                $sks = isset($mk->sks) ? strval($mk->sks) : '';
            } else {
                return "- unknown";
            }
            return "- {$id}: {$nama} ({$sks} SKS)";
        })->implode("\n");

        $prompt = "Tentukan mata kuliah yang paling setara dari daftar kurikulum tujuan berikut untuk mata kuliah asal: \"{$mkAsal}\".\n\n"
                . "Daftar Kurikulum Tujuan:\n{$kurikulumList}\n\n"
                . "Aturan:\n"
                . "1. Balas hanya dengan JSON format: {\"id\": \"UUID\", \"reason\": \"Alasan singkat\"}\n"
                . "2. Jika tidak ada yang cocok, balas: {\"id\": null, \"reason\": \"Tidak ada kecocokan\"}\n"
                . "3. Fokus pada kesamaan materi/substansi.";

        try {
            $response = Http::withToken($apiKey)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an academic expert in course credit transfer.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'response_format' => ['type' => 'json_object']
                ]);

            if ($response->successful()) {
                $contentRaw = $response->json('choices.0.message.content');
                $content = is_string($contentRaw) ? $contentRaw : '';
                
                /** @var array<string, mixed>|null $decoded */
                $decoded = json_decode($content, true);
                return $decoded;
            }

            Log::error('Sumopod API Error: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Sumopod Exception: ' . $e->getMessage());
        }

        return null;
    }
}
