<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Prodi;
use App\Models\Pendaftar;
use App\Models\TranskripAsal;
use Illuminate\Http\JsonResponse;
use App\Models\User;

class ScannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
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

    public function saveScan(Request $request): JsonResponse
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

        /** @var User $user */
        $user = $request->user();
        $id_kampus = $user->id_kampus;

        try {
            return DB::transaction(function () use ($request, $id_kampus) {
                // Generate ID Unik APL_YYMMDDXXXX
                $id_pendaftar = 'APL_' . date('ymd') . rand(1000, 9999);

                Pendaftar::create([
                    'id' => $id_pendaftar,
                    'id_kampus' => $id_kampus,
                    'id_prodi' => $request->input('id_prodi'),
                    'nama_lengkap' => $request->input('nama_lengkap'),
                    'email' => $request->input('email'),
                    'no_whatsapp' => $request->input('no_whatsapp'),
                    'asal_kampus' => $request->input('asal_kampus'),
                    'jalur_masuk' => 'walk_in',
                    'status' => 'Pending Kaprodi',
                    'total_sks_diakui' => 0,
                ]);

                /** @var array<int, array<string, mixed>> $matches */
                $matches = $request->input('matches');
                foreach ($matches as $match) {
                    $mkAsal = $match['mk_asal'];
                    $sksAsal = $match['sks_asal'];
                    $nilaiAsal = $match['nilai_asal'];

                    TranskripAsal::create([
                        'id_pendaftar' => $id_pendaftar,
                        'nama_mk_asal' => is_scalar($mkAsal) ? (string) $mkAsal : '',
                        'sks_asal' => is_numeric($sksAsal) ? (int) $sksAsal : 0,
                        'nilai_huruf_asal' => is_scalar($nilaiAsal) ? (string) $nilaiAsal : '',
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

    public function autoMatch(Request $request): JsonResponse
    {
        $request->validate([
            'id_prodi' => 'required|exists:prodi,id',
            'mk_asal' => 'required|string',
        ]);

        $mkAsalInput = $request->input('mk_asal');
        $mkAsal = strtolower(is_scalar($mkAsalInput) ? (string)$mkAsalInput : '');
        $idProdi = $request->input('id_prodi');
        
        // 1. Cari via MkReferensiAi
        $match = \App\Models\MkReferensiAi::where('is_active', true)
            ->whereHas('mataKuliah', function($q) use ($idProdi) {
                $q->where('id_prodi', $idProdi);
            })
            ->where(function($q) use ($mkAsal) {
                $q->where('keyword', 'like', "%{$mkAsal}%")
                  ->orWhere('keyword_normalized', 'like', "%{$mkAsal}%");
            })
            ->with('mataKuliah')
            ->orderBy('weight', 'desc')
            ->first();

        if ($match instanceof \App\Models\MkReferensiAi) {
            /** @var \App\Models\KurikulumMk $mataKuliah */
            $mataKuliah = $match->mataKuliah;
            return response()->json([
                'success' => true,
                'match' => [
                    'id_mk_tujuan' => $match->id_kurikulum_mk,
                    'nama_mk' => $mataKuliah->nama_mk,
                    'sks' => $mataKuliah->sks,
                    'score' => $match->weight,
                    'method' => 'AI Reference'
                ]
            ]);
        }

        // 2. Fallback: Simple keyword match ke nama_mk langsung
        $fallback = \App\Models\KurikulumMk::where('id_prodi', $idProdi)
            ->where('nama_mk', 'like', "%{$mkAsal}%")
            ->first();

        if ($fallback instanceof \App\Models\KurikulumMk) {
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
