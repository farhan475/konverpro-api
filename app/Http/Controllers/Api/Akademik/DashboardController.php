<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pendaftar;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $id_kampus = $user->id_kampus;

        $stats = [
            'total_pendaftar' => 0,
            'antrean_review' => 0,
            'menunggu_kaprodi' => 0,
            'konversi_selesai' => 0
        ];

        $pendaftar_counts = Pendaftar::where('id_kampus', $id_kampus)
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->get();

        foreach ($pendaftar_counts as $row) {
            $stats['total_pendaftar'] += $row->jumlah;
            $status = strtolower($row->status);

            if (in_array($status, ['baru', 'review akademik', 'ai processing'])) {
                $stats['antrean_review'] += $row->jumlah;
            } elseif ($status == 'pending kaprodi') {
                $stats['menunggu_kaprodi'] += $row->jumlah;
            } elseif ($status == 'approved') {
                $stats['konversi_selesai'] += $row->jumlah;
            }
        }

        return response()->json([
            'stats' => $stats,
            'nama_user' => $user->nama_lengkap
        ], 200);
    }
}
