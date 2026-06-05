<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pendaftar;
use Illuminate\Http\JsonResponse;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $id_kampus = $user->id_kampus;

        $stats = [
            'total_pendaftar' => 0,
            'antrean_review' => 0,
            'menunggu_kaprodi' => 0,
            'konversi_selesai' => 0
        ];

        /** @var \Illuminate\Database\Eloquent\Collection<int, Pendaftar> $pendaftar_counts */
        $pendaftar_counts = Pendaftar::where('id_kampus', $id_kampus)
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->get();

        foreach ($pendaftar_counts as $row) {
            $val = $row->getAttribute('jumlah');
            $jumlah = is_numeric($val) ? (int) $val : 0;
            
            $stats['total_pendaftar'] += $jumlah;
            $status = strtolower((string) $row->status);

            if (in_array($status, ['baru', 'review akademik', 'ai processing'])) {
                $stats['antrean_review'] += $jumlah;
            } elseif ($status == 'pending kaprodi') {
                $stats['menunggu_kaprodi'] += $jumlah;
            } elseif ($status == 'approved') {
                $stats['konversi_selesai'] += $jumlah;
            }
        }

        return response()->json([
            'stats' => $stats,
            'nama_user' => $user->nama_lengkap
        ], 200);
    }
}
