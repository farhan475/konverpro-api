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
        
        // Ambil kurikulum prodi tujuan
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
            // Update status
            $pendaftar->update([
                'status' => 'Approved',
                'hash_ba_digital' => $request->hash_ba_digital
            ]);

            // Save results
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
            
            $pendaftar->update(['total_sks_diakui' => $total_sks]);\n\n            // Kirim Notifikasi Approved\n            \\App\\Services\\NotificationService::send(\n                'pendaftar_approved',\n                $pendaftar->email,\n                $pendaftar->no_whatsapp,\n                [\n                    'nama' => $pendaftar->nama_lengkap,\n                    'id' => $pendaftar->id,\n                    'sks' => $total_sks,\n                    'status' => 'APPROVED',\n                    'kampus' => $pendaftar->prodi->kampus->nama_kampus ?? 'Kampus'\n                ]\n            );\n        });\n
        return response()->json([
            'success' => true,
            'message' => 'Validasi berhasil disimpan.'
        ]);
    }

    public function printData($id)\n    {\n        $pendaftar = Pendaftar::with(['prodi.kampus'])->findOrFail($id);\n        $hasil = HasilKonversi::where('id_pendaftar', $id)\n            ->join('kurikulum_mk', 'hasil_konversi.id_mk_tujuan', '=', 'kurikulum_mk.id')\n            ->join('transkrip_asal', 'hasil_konversi.id_transkrip_asal', '=', 'transkrip_asal.id')\n            ->select(\n                'hasil_konversi.*',\n                'kurikulum_mk.nama_mk as nama_mk_tujuan',\n                'kurikulum_mk.kode_mk as kode_mk_tujuan',\n                'kurikulum_mk.sks as sks_tujuan',\n                'transkrip_asal.nama_mk_asal'\n            )\n            ->get();\n\n        return response()->json([\n            'success' => true,\n            'data' => [\n                'pendaftar' => $pendaftar,\n                'kampus' => $pendaftar->prodi->kampus,\n                'prodi' => $pendaftar->prodi,\n                'hasil' => $hasil\n            ]\n        ]);\n    }\n\n    public function downloadPdf($id)\n    {\n        $pendaftar = Pendaftar::with(['prodi.kampus'])->findOrFail($id);\n        $hasil = HasilKonversi::where('id_pendaftar', $id)\n            ->join('kurikulum_mk', 'hasil_konversi.id_mk_tujuan', '=', 'kurikulum_mk.id')\n            ->join('transkrip_asal', 'hasil_konversi.id_transkrip_asal', '=', 'transkrip_asal.id')\n            ->select(\n                'hasil_konversi.*',\n                'kurikulum_mk.nama_mk as nama_mk_tujuan',\n                'kurikulum_mk.kode_mk as kode_mk_tujuan',\n                'kurikulum_mk.sks as sks_tujuan',\n                'transkrip_asal.nama_mk_asal'\n            )\n            ->get();\n\n        $dompdf = new \\Dompdf\\Dompdf(['isRemoteEnabled' => true]);\n        $html = view('pdf.berita-acara', [\n            'pendaftar' => $pendaftar,\n            'kampus' => $pendaftar->prodi->kampus,\n            'prodi' => $pendaftar->prodi,\n            'hasil' => $hasil\n        ])->render();\n\n        $dompdf->loadHtml($html);\n        $dompdf->setPaper('A4', 'portrait');\n        $dompdf->render();\n\n        return response($dompdf->output(), 200, [\n            'Content-Type' => 'application/pdf',\n            'Content-Disposition' => 'inline; filename=\"Berita_Acara_' . $pendaftar->id . '.pdf\"'\n        ]);\n    }\n}
