<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Prodi;
use App\Models\Pendaftar;
use App\Models\TranskripAsal;

class ScannerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $id_kampus = $user->id_kampus;

        if (!$id_kampus) {
            return response()->json(['message' => 'User tidak terasosiasi dengan kampus manapun.'], 403);
        }

        $prodi = Prodi::where('id_kampus', $id_kampus)
            ->with(['kurikulum:id,id_prodi,nama_mk,sks,semester'])
            ->get(['id', 'nama_prodi', 'jenjang']);

        return response()->json([
            'success' => true,
            'data' => $prodi
        ], 200);
    }

    public function saveScan(Request $request)
    {
        $request->validate([
            'id_prodi' => 'required|exists:prodi,id',
            'nama_lengkap' => 'required|string|max:150',
            'email' => 'nullable|email|max:100',
            'no_whatsapp' => 'nullable|string|max:20',
            'asal_kampus' => 'nullable|string|max:150',
            'matches' => 'required|array',
            'matches.*.mk_asal' => 'required|string',
            'matches.*.sks_asal' => 'required|integer',
            'matches.*.nilai_asal' => 'required|string|max:5',
        ]);

        $user = $request->user();
        $id_kampus = $user->id_kampus;

        try {
            return DB::transaction(function () use ($request, $id_kampus) {
                // Generate ID Unik APL_YYMMDDXXXX
                $id_pendaftar = 'APL_' . date('ymd') . rand(1000, 9999);

                $pendaftar = Pendaftar::create([
                    'id' => $id_pendaftar,
                    'id_kampus' => $id_kampus,
                    'id_prodi' => $request->id_prodi,
                    'nama_lengkap' => $request->nama_lengkap,
                    'email' => $request->email,
                    'no_whatsapp' => $request->no_whatsapp,
                    'asal_kampus' => $request->asal_kampus,
                    'jalur_masuk' => 'walk_in',
                    'status' => 'Pending Kaprodi',
                    'total_sks_diakui' => 0,
                ]);

                foreach ($request->matches as $match) {
                    TranskripAsal::create([
                        'id_pendaftar' => $id_pendaftar,
                        'nama_mk_asal' => $match['mk_asal'],
                        'sks_asal' => $match['sks_asal'],
                        'nilai_huruf_asal' => $match['nilai_asal'],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Transkrip asal berhasil diverifikasi Akademik dan diteruskan ke Kaprodi.',
                    'id_pendaftar' => $id_pendaftar
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function autoMatch(Request $request)
    {
        $request->validate([
            'id_prodi' => 'required|exists:prodi,id',
            'mk_asal' => 'required|string',
        ]);

        $mkAsal = strtolower($request->mk_asal);
        
        // 1. Cari via MkReferensiAi
        $match = \App\Models\MkReferensiAi::where('is_active', true)
            ->whereHas('mataKuliah', function($q) use ($request) {
                $q->where('id_prodi', $request->id_prodi);
            })
            ->where(function($q) use ($mkAsal) {
                $q->where('keyword', 'like', "%{$mkAsal}%")
                  ->orWhere('keyword_normalized', 'like', "%{$mkAsal}%");
            })
            ->with('mataKuliah')
            ->orderBy('weight', 'desc')
            ->first();

        if ($match) {
            return response()->json([
                'success' => true,
                'match' => [
                    'id_mk_tujuan' => $match->id_kurikulum_mk,
                    'nama_mk' => $match->mataKuliah->nama_mk,
                    'sks' => $match->mataKuliah->sks,
                    'score' => $match->weight,
                    'method' => 'AI Reference'
                ]
            ]);
        }

        // 2. Fallback: Simple keyword match ke nama_mk langsung
        $fallback = \App\Models\KurikulumMk::where('id_prodi', $request->id_prodi)
            ->where('nama_mk', 'like', "%{$mkAsal}%")
            ->first();

        if ($fallback) {
            return response()->json([
                'success' => true,
                'match' => [
                    'id_mk_tujuan' => $fallback->id,
                    'nama_mk' => $fallback->nama_mk,
                    'sks' => $fallback->sks,
                    'score' => 60,
                    'method' => 'String Match'
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Tidak ditemukan padanan yang cocok.'
        ]);
    }
}
