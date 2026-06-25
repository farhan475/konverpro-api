<?php

namespace App\Services;

use App\Models\KurikulumMk;
use App\Models\PengaturanGlobal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SumopodService
{
    /**
     * @param  array<int, KurikulumMk>  $kurikulumOptions
     * @return array{id: string|null, reason: string}|null
     */
    public function getAiMatch(string $mkAsal, array $kurikulumOptions): ?array
    {
        $apiKey = PengaturanGlobal::get('sumopod_api_key');
        $baseUrl = PengaturanGlobal::get('sumopod_base_url', 'https://api.sumopod.com/v1');
        $model = PengaturanGlobal::get('sumopod_model', 'gpt-4o-mini');

        if (empty($apiKey)) {
            Log::warning('SumopodService: API key belum dikonfigurasi.');

            return null;
        }

        $kurikulumList = collect($kurikulumOptions)
            ->map(fn ($mk) => "- {$mk->id}: {$mk->nama_mk} ({$mk->sks} SKS)")
            ->implode("\n");

        $prompt = "Tentukan mata kuliah yang paling setara dari daftar berikut untuk mata kuliah: \"{$mkAsal}\".\n\n"
            ."Daftar:\n{$kurikulumList}\n\n"
            ."Balas HANYA JSON: {\"id\": \"UUID atau null\", \"reason\": \"alasan singkat Bahasa Indonesia\"}\n"
            .'Jika tidak ada yang cocok: {"id": null, "reason": "Tidak ada kecocokan"}';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an academic expert in university course credit transfer.'],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => 150,
                    'temperature' => 0,
                ]);

            if (! $response->successful()) {
                Log::error('SumopodService: API error', ['status' => $response->status()]);

                return null;
            }

            $content = $response->json('choices.0.message.content', '{}');
            $decoded = json_decode(is_string($content) ? $content : '{}', true);

            if (! is_array($decoded) || ! array_key_exists('id', $decoded)) {
                Log::warning('SumopodService: Respons tidak valid', ['content' => $content]);

                return null;
            }

            return [
                'id' => isset($decoded['id']) && is_string($decoded['id']) ? $decoded['id'] : null,
                'reason' => isset($decoded['reason']) && is_string($decoded['reason']) ? $decoded['reason'] : '',
            ];
        } catch (\Exception $e) {
            Log::error('SumopodService: Exception', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
