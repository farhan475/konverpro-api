<?php

namespace App\Services;

use App\Models\KamusSinonim;
use Illuminate\Validation\ValidationException;

class KamusSinonimService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data): array
    {
        foreach (['kata_utama', 'sinonim'] as $field) {
            $value = $data[$field] ?? '';
            if (is_string($value)) {
                $data[$field] = mb_strtolower(trim($value));
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function ensureUnique(array $data, ?KamusSinonim $except = null): void
    {
        $query = KamusSinonim::query()
            ->where('kata_utama', $data['kata_utama'])
            ->where('sinonim', $data['sinonim']);

        if ($except) {
            $query->whereKeyNot($except->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'sinonim' => 'Pasangan ini sudah ada di kamus.',
            ]);
        }
    }
}
