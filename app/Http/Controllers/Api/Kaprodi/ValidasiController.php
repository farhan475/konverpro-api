<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pendaftar;
use App\Models\HasilKonversi;
use App\Models\Prodi;
use App\Models\KurikulumMk;
use App\Models\PengaturanGlobal;

class ValidasiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $id_kaprodi = $user->id;

        $pendaftar = Pendaftar::whereHas('prodi', function ($query) use ($id_kaprodi) {
                $query->where('id_kaprodi', $id_kaprodi);
            })
            ->with(['prodi', 'transkripAsal', 'hasilKonversi.mkTujuan'])
            ->orderByRaw("CASE WHEN status = 'Pending Kaprodi' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($pendaftar);
    }

    public function process(Request $request, $id_pendaftar)
    {
        $validated = $request->validate([
            'status' => 'required|in:Approved,Revisi,Rejected,Pending Kaprodi',
            'catatan' => 'nullable|string',
            'mappings' => 'nullable|array', // Data pemetaan manual dari Kaprodi
            'mappings.*.id_mk_tujuan' => 'required|exists:kurikulum_mk,id',
            'mappings.*.id_transkrip_asal' => 'nullable|exists:transkrip_asal,id',
            'mappings.*.nilai_akhir_huruf' => 'required|string|max:5',
            'mappings.*.sks_diakui' => 'required|integer',
            'mappings.*.metode_pemetaan' => 'required|in:AI,Manual,Manual Kaprodi',
        ]);

        $user = $request->user();
        $pendaftar = Pendaftar::where('id', $id_pendaftar)
            ->whereHas('prodi', function ($query) use ($user) {
                $query->where('id_kaprodi', $user->id);
            })
            ->firstOrFail();

        try {
            DB::beginTransaction();

            // 1. Update Pemetaan (jika ada input mappings baru)
            if ($request->has('mappings')) {
                // Hapus pemetaan lama jika Kaprodi melakukan pemetaan ulang manual
                HasilKonversi::where('id_pendaftar', $id_pendaftar)->delete();

                $total_sks_diakui = 0;
                foreach ($validated['mappings'] as $map) {
                    HasilKonversi::create(array_merge($map, [
                        'id_pendaftar' => $id_pendaftar,
                        'metode_pemetaan' => 'Manual Kaprodi'
                    ]));
                    $total_sks_diakui += $map['sks_diakui'];
                }
                
                $pendaftar->total_sks_diakui = $total_sks_diakui;
            }

            // 2. Logika Billing dicopot sesuai instruksi user sebelumnya (untuk Billing & Mitra jangan ditambahkan)
            // Namun, jika sistem membutuhkan billing untuk fungsionalitas, kita bisa menambahkan pengecekan di sini nanti.
            // Untuk saat ini, kita ikuti instruksi "jangan ditambahkan" untuk billing/mitra.

            // 3. Update Status Pendaftar
            $pendaftar->status = $validated['status'];
            $pendaftar->catatan_revisi = $validated['catatan'];
            $pendaftar->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Keputusan ({$validated['status']}) berhasil disimpan.",
                'data' => $pendaftar
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses validasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengambil data detail pendaftar untuk kebutuhan UI modal review
     */
    public function show(Request $request, $id_pendaftar)
    {
        $user = $request->user();
        $pendaftar = Pendaftar::where('id', $id_pendaftar)
            ->whereHas('prodi', function ($query) use ($user) {
                $query->where('id_kaprodi', $user->id);
            })
            ->with(['prodi', 'transkripAsal', 'hasilKonversi.mkTujuan'])
            ->firstOrFail();

        // Ambil kurikulum prodi pendaftar untuk pilihan dropdown manual matching
        $kurikulum = KurikulumMk::where('id_prodi', $pendaftar->id_prodi)
            ->orderBy('semester')
            ->orderBy('nama_mk')
            ->get();

        return response()->json([
            'pendaftar' => $pendaftar,
            'kurikulum_prodi' => $kurikulum
        ]);
    }
}
