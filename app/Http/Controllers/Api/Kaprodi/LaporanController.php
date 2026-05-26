<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pendaftar;
use App\Models\Prodi;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $prodi_ids = Prodi::where('id_kaprodi', $user->id)->pluck('id');

        $stats = [
            'total' => Pendaftar::whereIn('id_prodi', $prodi_ids)->count(),
            'approved' => Pendaftar::whereIn('id_prodi', $prodi_ids)->where('status', 'Approved')->count(),
        ];

        $laporan = Pendaftar::whereIn('id_prodi', $prodi_ids)
            ->where('status', 'Approved')
            ->with('prodi')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'stats' => $stats,
            'laporan' => $laporan
        ]);
    }
}
