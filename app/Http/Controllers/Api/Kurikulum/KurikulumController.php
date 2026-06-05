<?php

namespace App\Http\Controllers\Api\Kurikulum;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KurikulumMk;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class KurikulumController extends Controller
{
    /**
     * Menampilkan daftar mata kuliah kurikulum.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $id_kampus = $user->id_kampus;

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
    public function store(Request $request): JsonResponse
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

        /** @var User $user */
        $user = $request->user();

        // Pastikan prodi milik kampus user
        Prodi::where('id', $validated['id_prodi'])
            ->where('id_kampus', $user->id_kampus)
            ->firstOrFail();

        $mk = KurikulumMk::create($validated);

        return response()->json($mk, 201);
    }

    /**
     * Memperbarui mata kuliah.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $id_kampus = $user->id_kampus;

        $mk = KurikulumMk::where('id', $id)
            ->whereHas('prodi', function($q) use ($id_kampus) {
                $q->where('id_kampus', $id_kampus);
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
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $id_kampus = $user->id_kampus;

        $mk = KurikulumMk::where('id', $id)
            ->whereHas('prodi', function($q) use ($id_kampus) {
                $q->where('id_kampus', $id_kampus);
            })
            ->firstOrFail();

        $mk->delete();

        return response()->json(['message' => 'Mata kuliah berhasil dihapus.']);
    }

    /**
     * Import mata kuliah massal.
     */
    public function import(\App\Http\Requests\Kurikulum\ImportKurikulumRequest $request, \App\Services\Kurikulum\ImportKurikulumService $service): JsonResponse
    {
        /** @var array<int, array<string, mixed>> $excelData */
        $excelData = $request->input('excel_data', []);
        $idProdiInput = $request->input('id_prodi');
        $idProdi = is_numeric($idProdiInput) ? intval($idProdiInput) : 0;
        $count = $service->import($idProdi, $excelData);
        return response()->json(['message' => "{$count} mata kuliah berhasil diimpor."], 201);
    }
}
