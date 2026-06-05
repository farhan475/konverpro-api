<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KurikulumMk;

class PemetaanController extends Controller
{
    /**
     * Kaprodi hanya bisa MELIHAT (Read-Only) daftar mata kuliah kurikulum.
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        
        $kurikulum = KurikulumMk::whereHas('prodi', function($q) use ($user) {
                $q->where('id_kaprodi', $user->id);
            })
            ->with('prodi')
            ->orderBy('semester')
            ->orderBy('nama_mk')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $kurikulum
        ]);
    }

    /**
     * Kaprodi tidak memiliki akses untuk menambah MK (Sudah dipindah ke role Kurikulum).
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => 'Hanya role Kurikulum yang dapat menambah mata kuliah.'], 403);
    }

    /**
     * Kaprodi tidak memiliki akses untuk mengubah MK.
     */
    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => 'Hanya role Kurikulum yang dapat mengubah mata kuliah.'], 403);
    }

    /**
     * Kaprodi tidak memiliki akses untuk menghapus MK.
     */
    public function destroy(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => 'Hanya role Kurikulum yang dapat menghapus mata kuliah.'], 403);
    }
}
