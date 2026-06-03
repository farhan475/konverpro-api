<?php

namespace App\Http\Controllers\Api\Kurikulum;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KurikulumMk;
use App\Models\Prodi;

class KurikulumController extends Controller
{
    /**
     * Menampilkan daftar mata kuliah kurikulum.
     */
    public function index(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;
        $kurikulum = KurikulumMk::whereHas('prodi', function($q) use ($id_kampus) {
                $q->where('id_kampus', $id_kampus);
            })
            ->with('prodi')
            ->orderBy('id_prodi')
            ->orderBy('semester')
            ->get();

        return response()->json($kurikulum);
    }

    /**
     * Menambah mata kuliah baru (Hanya untuk role Kurikulum).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_prodi' => 'required|exists:prodi,id',
            'kode_mk' => 'required|string|max:20',
            'nama_mk' => 'required|string|max:150',
            'sks' => 'required|integer|min:1',
            'semester' => 'required|integer|min:1',
            'tipe_mk' => 'required|in:Wajib,Pilihan',
            'deskripsi_singkat' => 'nullable|string',
        ]);

        // Pastikan prodi milik kampus user
        $prodi = Prodi::where('id', $validated['id_prodi'])
            ->where('id_kampus', $request->user()->id_kampus)
            ->firstOrFail();

        $mk = KurikulumMk::create($validated);

        return response()->json($mk, 201);
    }

    /**
     * Memperbarui mata kuliah.
     */
    public function update(Request $request, $id)
    {
        $mk = KurikulumMk::where('id', $id)
            ->whereHas('prodi', function($q) use ($request) {
                $q->where('id_kampus', $request->user()->id_kampus);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'id_prodi' => 'required|exists:prodi,id',
            'kode_mk' => 'required|string|max:20',
            'nama_mk' => 'required|string|max:150',
            'sks' => 'required|integer|min:1',
            'semester' => 'required|integer|min:1',
            'tipe_mk' => 'required|in:Wajib,Pilihan',
            'deskripsi_singkat' => 'nullable|string',
        ]);

        $mk->update($validated);

        return response()->json($mk);
    }


    /**
     * Menghapus mata kuliah.
     */
    public function destroy(Request $request, $id)
    {
        $mk = KurikulumMk::where('id', $id)
            ->whereHas('prodi', function($q) use ($request) {
                $q->where('id_kampus', $request->user()->id_kampus);
            })
            ->firstOrFail();

        $mk->delete();

        return response()->json(['message' => 'Mata kuliah berhasil dihapus.']);
    }

    /**
     * Import mata kuliah massal.
     */
    public function import(\App\Http\Requests\Kurikulum\ImportKurikulumRequest $request, \App\Services\Kurikulum\ImportKurikulumService $service)
    {
        $count = $service->import($request->id_prodi, $request->excel_data);
        return response()->json(['message' => "{$count} mata kuliah berhasil diimpor."], 201);
    }
}
