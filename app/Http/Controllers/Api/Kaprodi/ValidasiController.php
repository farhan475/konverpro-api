<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pendaftar;
use App\Models\KurikulumMk;
use App\Models\HasilKonversi;
use App\Models\Prodi;

class ValidasiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $prodi_ids = Prodi::where('id_kaprodi', $user->id)->pluck('id');

        $pendaftar = Pendaftar::whereIn('id_prodi', $prodi_ids)
            ->whereIn('status', ['Pending Kaprodi', 'Approved', 'Revisi'])
            ->with('prodi')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pendaftar
        ]);
    }

    public function show($id)
    {
        $pendaftar = Pendaftar::with(['prodi', 'transkripAsal', 'hasilKonversi'])->findOrFail($id);
        
        $kurikulum = KurikulumMk::where('id_prodi', $pendaftar->id_prodi)
            ->orderBy('semester')
            ->orderBy('nama_mk')
            ->get();

        $data = $pendaftar->toArray();
        $data['transkrip_asal'] = $pendaftar->transkripAsal;
        $data['kurikulum_target'] = $kurikulum;
        $data['hasil_konversi'] = $pendaftar->hasilKonversi;

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function process(Request $request, $id)
    {
        $pendaftar = Pendaftar::findOrFail($id);
        
        DB::transaction(function () use ($pendaftar, $request) {
            $pendaftar->update([
                'status' => 'Approved',
                'hash_ba_digital' => $request->hash_ba_digital
            ]);

            HasilKonversi::where('id_pendaftar', $pendaftar->id)->delete();
            
            $total_sks = 0;
            foreach ($request->mapping as $item) {
                $mk_tujuan = KurikulumMk::find($item['id_mk_tujuan']);
                $sks = $mk_tujuan ? $mk_tujuan->sks : 0;
                
                HasilKonversi::create([
                    'id_pendaftar' => $pendaftar->id,
                    'id_mk_tujuan' => $item['id_mk_tujuan'],
                    'id_transkrip_asal' => $item['id_transkrip_asal'],
                    'nilai_akhir_huruf' => $item['nilai_akhir_huruf'],
                    'sks_diakui' => $sks,
                    'metode_pemetaan' => 'Manual Kaprodi',
                ]);
                $total_sks += $sks;
            }
            
            $pendaftar->update(['total_sks_diakui' => $total_sks]);

            // Kirim Notifikasi Approved
            \App\Services\NotificationService::send(
                'pendaftar_approved',
                $pendaftar->email,
                $pendaftar->no_whatsapp,
                [
                    'nama' => $pendaftar->nama_lengkap,
                    'id' => $pendaftar->id,
                    'sks' => $total_sks,
                    'status' => 'APPROVED',
                    'kampus' => $pendaftar->prodi->kampus->nama_kampus ?? 'Kampus'
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Validasi berhasil disimpan.'
        ]);
    }

    /**
     * Bulk Process untuk menyetujui banyak mahasiswa sekaligus (Asumsi AI sudah memetakan)
     */
    public function bulkProcess(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:pendaftar,id',
            'hash_ba_digital' => 'required|string'
        ]);

        $count = 0;
        foreach ($request->ids as $id) {
            $pendaftar = Pendaftar::where('id', $id)->where('status', 'Pending Kaprodi')->first();
            if ($pendaftar) {
                $pendaftar->update([
                    'status' => 'Approved',
                    'hash_ba_digital' => $request->hash_ba_digital
                ]);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$count} pendaftar secara massal.",
        ]);
    }

    public function printData($id)
    {
        $pendaftar = Pendaftar::with(['prodi.kampus'])->findOrFail($id);
        $hasil = HasilKonversi::where('id_pendaftar', $id)
            ->join('kurikulum_mk', 'hasil_konversi.id_mk_tujuan', '=', 'kurikulum_mk.id')
            ->join('transkrip_asal', 'hasil_konversi.id_transkrip_asal', '=', 'transkrip_asal.id')
            ->select(
                'hasil_konversi.*',
                'kurikulum_mk.nama_mk as nama_mk_tujuan',
                'kurikulum_mk.kode_mk as kode_mk_tujuan',
                'kurikulum_mk.sks as sks_tujuan',
                'transkrip_asal.nama_mk_asal'
            )
            ->get();

        /** @var \App\Models\Pendaftar $pendaftar */
        /** @var \App\Models\Prodi $prodi */
        $prodi = $pendaftar->prodi;
        return response()->json([
            'success' => true,
            'data' => [
                'pendaftar' => $pendaftar,
                'kampus' => $prodi->kampus,
                'prodi' => $prodi,
                'hasil' => $hasil
            ]
        ]);
    }

    public function downloadPdf($id)
    {
        $pendaftar = Pendaftar::with(['prodi.kampus'])->findOrFail($id);
        $hasil = HasilKonversi::where('id_pendaftar', $id)
            ->join('kurikulum_mk', 'hasil_konversi.id_mk_tujuan', '=', 'kurikulum_mk.id')
            ->join('transkrip_asal', 'hasil_konversi.id_transkrip_asal', '=', 'transkrip_asal.id')
            ->select(
                'hasil_konversi.*',
                'kurikulum_mk.nama_mk as nama_mk_tujuan',
                'kurikulum_mk.kode_mk as kode_mk_tujuan',
                'kurikulum_mk.sks as sks_tujuan',
                'transkrip_asal.nama_mk_asal'
            )
            ->get();

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        /** @var \App\Models\Pendaftar $pendaftar */
        /** @var \App\Models\Prodi $prodi */
        $prodi = $pendaftar->prodi;
        /** @var view-string $viewName */
        $viewName = 'pdf.berita-acara';
        $html = view($viewName, [
            'pendaftar' => $pendaftar,
            'kampus' => $prodi->kampus,
            'prodi' => $prodi,
            'hasil' => $hasil
        ])->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Berita_Acara_' . $pendaftar->id . '.pdf"'
        ]);
    }
}
