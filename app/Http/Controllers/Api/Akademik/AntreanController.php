<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pendaftar;

class AntreanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $id_kampus = $user->id_kampus;

        if (!$id_kampus) {
            return response()->json(['message' => 'User tidak terasosiasi dengan kampus manapun.'], 403);
        }

        $pendaftar = Pendaftar::where('id_kampus', $id_kampus)
            ->with(['prodi:id,nama_prodi'])
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'total' => Pendaftar::where('id_kampus', $id_kampus)->count(),
            'baru' => Pendaftar::where('id_kampus', $id_kampus)->where('status', 'Baru')->count(),
            'proses' => Pendaftar::where('id_kampus', $id_kampus)->whereIn('status', ['AI Processing', 'Review Akademik'])->count(),
            'selesai' => Pendaftar::where('id_kampus', $id_kampus)->where('status', 'Approved')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $pendaftar,
            'stats' => $stats
        ], 200);
    }

    public function show(Request $request, $id)
    {
        $id_kampus = $request->user()->id_kampus;
        $pendaftar = Pendaftar::where('id', $id)
            ->where('id_kampus', $id_kampus)
            ->with(['prodi', 'transkripAsal'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $pendaftar
        ]);
    }
}
