<?php

namespace App\Services;

use App\Models\PengaturanGlobal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SumopodService
{
    /**
     * Minta AI menentukan MK tujuan terbaik dari daftar kurikulum.
     *
     * @param  string  $mkAsal
     * @param  array<int, \App\Models\KurikulumMk>  $kurikulumOptions
     * @return array{id: string|null, reason: string}|null  null jika API gagal total
     */
    public function getAiMatch(string $mkAsal, array $kurikulumOptions): ?array
    {
        // Baca via PengaturanGlobal::get() agar key sensitif didekripsi
        $apiKey  = PengaturanGlobal::get('sumopod_api_key');
        $baseUrl = PengaturanGlobal::get('sumopod_base_url', 'https://api.openai.com/v1');
        $model   = PengaturanGlobal::get('sumopod_model', 'gpt-4o-mini');

        if (empty($apiKey)) {
            Log::warning('SumopodService: API key belum dikonfigurasi.');
            return null;
        }

        $kurikulumList = collect($kurikulumOptions)
            ->map(fn($mk) => "- {$mk->id}: {$mk->nama_mk} ({$mk->sks} SKS)")
            ->implode("\n");

        $prompt = "Tentukan mata kuliah yang paling setara dari daftar kurikulum tujuan berikut "
                . "untuk mata kuliah asal: \"{$mkAsal}\".\n\n"
                . "Daftar Kurikulum Tujuan:\n{$kurikulumList}\n\n"
                . "Aturan:\n"
                . "1. Balas HANYA dengan JSON: {\"id\": \"UUID\", \"reason\": \"alasan singkat dalam Bahasa Indonesia\"}\n"
                . "2. Jika tidak ada yang cocok: {\"id\": null, \"reason\": \"Tidak ada kecocokan\"}\n"
                . "3. Fokus pada kesamaan materi/substansi, bukan nama.";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post("{$baseUrl}/chat/completions", [
                    'model'           => $model,
                    'messages'        => [
                        ['role' => 'system', 'content' => 'You are an academic expert in university course credit transfer.'],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens'      => 150,
                    'temperature'     => 0,
                ]);

            if (!$response->successful()) {
                Log::error('SumopodService: API error', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $content = $response->json('choices.0.message.content', '{}');
            /** @var array{id?: string|null, reason?: string}|null $decoded */
            $decoded = json_decode(is_string($content) ? $content : '{}', true);

            if (!is_array($decoded) || !array_key_exists('id', $decoded)) {
                Log::warning('SumopodService: Respons tidak valid', ['content' => $content]);
                return null;
            }

            return [
                'id'     => isset($decoded['id']) && is_string($decoded['id']) ? $decoded['id'] : null,
                'reason' => isset($decoded['reason']) && is_string($decoded['reason']) ? $decoded['reason'] : '',
            ];

        } catch (\Exception $e) {
            Log::error('SumopodService: Exception', ['message' => $e->getMessage()]);
            return null;
        }
    }
}