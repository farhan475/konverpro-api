<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pendaftar;
use App\Models\KurikulumMk;
use App\Models\HasilKonversi;
use App\Models\Prodi;
use Illuminate\Http\JsonResponse;
use App\Models\User;

class ValidasiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
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

    public function show(string $id): JsonResponse
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

    public function process(Request $request, string $id): JsonResponse
    {
        $pendaftar = Pendaftar::findOrFail($id);
        
        DB::transaction(function () use ($pendaftar, $request) {
            $hash = $request->input('hash_ba_digital');
            $pendaftar->update([
                'status' => 'Approved',
                'hash_ba_digital' => is_scalar($hash) ? (string) $hash : null
            ]);

            HasilKonversi::where('id_pendaftar', $pendaftar->id)->delete();
            
            $total_sks = 0;
            /** @var array<int, array<string, mixed>> $mapping */
            $mapping = $request->input('mapping', []);
            foreach ($mapping as $item) {
                $mk_tujuan = KurikulumMk::find($item['id_mk_tujuan']);
                if ($mk_tujuan instanceof KurikulumMk) {
                    $sks = $mk_tujuan->sks;
                    $idMkAsal = $item['id_transkrip_asal'];
                    $nilai = $item['nilai_akhir_huruf'];

                    HasilKonversi::create([
                        'id_pendaftar' => $pendaftar->id,
                        'id_mk_tujuan' => is_numeric($item['id_mk_tujuan']) ? (int) $item['id_mk_tujuan'] : 0,
                        'id_transkrip_asal' => is_numeric($idMkAsal) ? (int) $idMkAsal : 0,
                        'nilai_akhir_huruf' => is_scalar($nilai) ? (string) $nilai : '',
                        'sks_diakui' => $sks,
                        'metode_pemetaan' => 'Manual Kaprodi',
                    ]);
                    $total_sks += $sks;
                }
            }
            
            $pendaftar->update(['total_sks_diakui' => $total_sks]);

            // Kirim Notifikasi Approved
            \App\Services\NotificationService::send(
                'pendaftar_approved',
                is_scalar($pendaftar->email) ? (string) $pendaftar->email : null,
                is_scalar($pendaftar->no_whatsapp) ? (string) $pendaftar->no_whatsapp : null,
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
     * Bulk Process untuk menyetujui banyak mahasiswa sekaligus
     */
    public function bulkProcess(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:pendaftar,id',
            'hash_ba_digital' => 'required|string'
        ]);

        $count = 0;
        /** @var array<int, string> $ids */
        $ids = $request->input('ids');
        $hashInput = $request->input('hash_ba_digital');
        $hash = is_scalar($hashInput) ? (string) $hashInput : '';

        foreach ($ids as $id) {
            $pendaftar = Pendaftar::where('id', $id)->where('status', 'Pending Kaprodi')->first();
            if ($pendaftar instanceof Pendaftar) {
                $pendaftar->update([
                    'status' => 'Approved',
                    'hash_ba_digital' => $hash
                ]);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$count} pendaftar secara massal.",
        ]);
    }

    public function printData(string $id): JsonResponse
    {
        $pendaftar = Pendaftar::with(['prodi.kampus'])->findOrFail($id);
        /** @var \App\Models\Prodi $prodi */
        $prodi = $pendaftar->prodi;
        
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

    public function downloadPdf(string $id): \Illuminate\Http\Response
    {
        $pendaftar = Pendaftar::with(['prodi.kampus'])->findOrFail($id);
        /** @var \App\Models\Prodi $prodi */
        $prodi = $pendaftar->prodi;

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

        /** @var string $output */
        $output = $dompdf->output();

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Berita_Acara_' . $pendaftar->id . '.pdf"'
        ]);
    }
}
