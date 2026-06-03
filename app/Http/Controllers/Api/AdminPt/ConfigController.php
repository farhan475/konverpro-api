<?php

namespace App\Http\Controllers\Api\AdminPt;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kampus;

class ConfigController extends Controller
{
    public function index(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;
        $kampus = Kampus::findOrFail($id_kampus);

        return response()->json([
            'success' => true,
            'data' => [
                'rektor_pimpinan' => $kampus->rektor_pimpinan,
                'no_telp' => $kampus->no_telp,
                'website' => $kampus->website,
                'alamat_resmi' => $kampus->alamat_resmi,
                'paket_layanan' => $kampus->paket_layanan,
                'is_official_partner' => $kampus->is_official_partner,
                'ai_config' => $kampus->ai_config ?? [
                    'engine' => 'konverpro',
                    'endpoint' => '',
                    'assistant_id' => 'sumopod-konversi-v1',
                    'api_key' => '',
                    'min_confidence' => 85,
                    'prompt' => "" // Will fall back to default if empty
                ],
            ]
        ]);
    }

    public function update(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;
        $kampus = Kampus::findOrFail($id_kampus);

        $validated = $request->validate([
            'rektor_pimpinan' => 'nullable|string|max:100',
            'no_telp' => 'nullable|string|max:20',
            'website' => 'nullable|string|max:100',
            'alamat_resmi' => 'nullable|string',
            'ai_config' => 'nullable|array',
        ]);

        $kampus->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi kampus berhasil diperbarui.'
        ]);
    }
}
