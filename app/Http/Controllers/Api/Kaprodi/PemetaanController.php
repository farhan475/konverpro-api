<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KurikulumMk;
use App\Models\Prodi;

class PemetaanController extends Controller
{
    /**
     * Kaprodi hanya bisa MELIHAT (Read-Only) daftar mata kuliah kurikulum.
     */
    public function index(Request $request)
    {
        $id_user = $request->user()->id;
        
        $kurikulum = KurikulumMk::whereHas('prodi', function($q) use ($id_user) {
                $q->where('id_kaprodi', $id_user);
            })
            ->with('prodi')
            ->orderBy('semester')
            ->orderBy('nama_mk')
            ->get();

        return response()->json($kurikulum);
    }

    /**
     * Kaprodi tidak memiliki akses untuk menambah MK (Sudah dipindah ke role Kurikulum).
     */
    public function store(Request $request)
    {
        return response()->json(['message' => 'Hanya role Kurikulum yang dapat menambah mata kuliah.'], 403);
    }

    /**
     * Kaprodi tidak memiliki akses untuk mengubah MK.
     */
    public function update(Request $request, $id)
    {
        return response()->json(['message' => 'Hanya role Kurikulum yang dapat mengubah mata kuliah.'], 403);
    }

    /**
     * Kaprodi tidak memiliki akses untuk menghapus MK.
     */
    public function destroy(Request $request, $id)
    {
        return response()->json(['message' => 'Hanya role Kurikulum yang dapat menghapus mata kuliah.'], 403);
    }
}
