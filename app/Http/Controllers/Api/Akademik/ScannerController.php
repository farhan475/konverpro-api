<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pendaftar;
use App\Models\TranskripAsal;
use App\Models\HasilKonversi;
use App\Models\Prodi;
use App\Models\KurikulumMk;
use App\Models\MkReferensiAi;
use Illuminate\Support\Str;

class ScannerController extends Controller
{
    public function index(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;
        $prodi = Prodi::where('id_kampus', $id_kampus)
            ->with(['kurikulumMk'])
            ->get();

        return response()->json($prodi);
    }

    public function saveScan(Request $request)
    {
        $validated = $request->validate([
            'id_prodi' => 'required|exists:prodi,id',
            'nama_lengkap' => 'required|string|max:150',
            'email' => 'nullable|email|max:100',
            'no_whatsapp' => 'nullable|string|max:20',
            'asal_kampus' => 'required|string|max:150',
            'matches' => 'required|array',
            'matches.*.mk_asal' => 'required|string',
            'matches.*.sks_asal' => 'required|integer',
            'matches.*.nilai_asal' => 'required|string',
        ]);

        $id_kampus = $request->user()->id_kampus;
        $id_pendaftar = 'APL_' . date('ymd') . Str::upper(Str::random(4));

        try {
            DB::beginTransaction();

            $pendaftar = Pendaftar::create([
                'id' => $id_pendaftar,
                'id_kampus' => $id_kampus,
                'id_prodi' => $validated['id_prodi'],
                'nama_lengkap' => $validated['nama_lengkap'],
                'email' => $validated['email'],
                'no_whatsapp' => $validated['no_whatsapp'],
                'asal_kampus' => $validated['asal_kampus'],
                'jalur_masuk' => 'walk_in',
                'status' => 'Pending Kaprodi',
                'total_sks_diakui' => 0,
            ]);

            foreach ($validated['matches'] as $match) {
                TranskripAsal::create([
                    'id_pendaftar' => $id_pendaftar,
                    'nama_mk_asal' => $match['mk_asal'],
                    'sks_asal' => $match['sks_asal'],
                    'nilai_huruf_asal' => $match['nilai_asal'],
                ]);
            }

            // Logika AI Matching Server-Side (Opsional, saat ini frontend sudah melakukan matching dasar)
            // Di sini kita bisa menambahkan logika matching yang lebih canggih menggunakan mk_referensi_ai jika perlu.

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pendaftaran dan verifikasi transkrip berhasil disimpan.',
                'id_pendaftar' => $id_pendaftar
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Algoritma AI Matching Server-Side menggunakan Keywords & Fuzzy Logic
     */
    public function autoMatch(Request $request)
    {
        $validated = $request->validate([
            'id_prodi' => 'required|exists:prodi,id',
            'nama_mk_asal' => 'required|string',
        ]);

        $mk_asal = Str::lower(trim($validated['nama_mk_asal']));
        $id_prodi = $validated['id_prodi'];

        // 1. Tahap Pertama: Pencocokan Kata Kunci (Keyword Matching)
        // Menggunakan mk_referensi_ai yang sudah didefinisikan secara manual untuk akurasi tinggi.
        $keywordMatches = MkReferensiAi::whereHas('kurikulumMk', function($q) use ($id_prodi) {
                $q->where('id_prodi', $id_prodi);
            })
            ->where('is_active', true)
            ->get()
            ->filter(function($ref) use ($mk_asal) {
                return Str::contains($mk_asal, Str::lower($ref->keyword_normalized));
            })
            ->sortByDesc('weight');

        if ($keywordMatches->isNotEmpty()) {
            $bestMatch = $keywordMatches->first()->kurikulumMk;
            return response()->json([
                'match' => $bestMatch,
                'score' => $keywordMatches->first()->weight,
                'method' => 'AI Keyword'
            ]);
        }

        // 2. Tahap Kedua: Fuzzy Logic Matching (String Similarity)
        // Menggunakan algoritma Levenshtein untuk menghitung jarak antar string.
        $kurikulum = KurikulumMk::where('id_prodi', $id_prodi)->get();
        $bestFuzzyMatch = null;
        $highestScore = 0;

        foreach ($kurikulum as $mk) {
            $mk_tujuan = Str::lower($mk->nama_mk);
            
            // Hitung skor similarity (0 - 100)
            $distance = levenshtein($mk_asal, $mk_tujuan);
            $maxLength = max(strlen($mk_asal), strlen($mk_tujuan));
            
            if ($maxLength == 0) continue;
            
            $similarity = (1 - ($distance / $maxLength)) * 100;

            if ($similarity > $highestScore) {
                $highestScore = $similarity;
                $bestFuzzyMatch = $mk;
            }
        }

        // Ambang batas (threshold) untuk fuzzy match adalah 65%
        if ($bestFuzzyMatch && $highestScore >= 65) {
            return response()->json([
                'match' => $bestFuzzyMatch,
                'score' => round($highestScore, 2),
                'method' => 'Fuzzy Logic'
            ]);
        }

        return response()->json([
            'match' => null,
            'message' => 'Tidak ada mata kuliah yang cukup mirip.'
        ], 404);
    }
}
