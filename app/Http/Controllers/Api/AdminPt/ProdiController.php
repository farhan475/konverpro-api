<?php

namespace App\Http\Controllers\Api\AdminPt;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prodi;
use App\Models\User;

class ProdiController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        $id_kampus = $user->id_kampus;
        
        $prodi = Prodi::where('id_kampus', $id_kampus)
            ->with(['kaprodi'])
            ->withCount(['kurikulum'])
            ->orderBy('jenjang')
            ->orderBy('nama_prodi')
            ->get();

        $kaprodi_options = User::where('id_kampus', $id_kampus)
            ->where('role', 'kaprodi')
            ->where('status', 'active')
            ->get(['id', 'nama_lengkap']);

        return response()->json([
            'success' => true,
            'data' => $prodi,
            'kaprodi_options' => $kaprodi_options
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        $id_kampus = $user->id_kampus;
        $validated = $request->validate([
            'id_kaprodi' => 'nullable|exists:users,id',
            'kode_prodi' => 'nullable|string|max:20',
            'nama_prodi' => 'required|string|max:100',
            'jenjang' => 'required|in:D3,D4,S1,S2',
            'biaya_pendaftaran' => 'nullable|numeric|min:0',
            'biaya_kuliah' => 'nullable|numeric|min:0',
        ]);

        $prodi = Prodi::create(array_merge($validated, ['id_kampus' => $id_kampus]));

        return response()->json([
            'success' => true,
            'message' => 'Program studi berhasil ditambahkan.',
            'data' => $prodi
        ], 201);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        $id_kampus = $user->id_kampus;
        $prodi = Prodi::where('id', $id)->where('id_kampus', $id_kampus)->firstOrFail();

        $validated = $request->validate([
            'id_kaprodi' => 'nullable|exists:users,id',
            'kode_prodi' => 'nullable|string|max:20',
            'nama_prodi' => 'required|string|max:100',
            'jenjang' => 'required|in:D3,D4,S1,S2',
            'biaya_pendaftaran' => 'nullable|numeric|min:0',
            'biaya_kuliah' => 'nullable|numeric|min:0',
        ]);

        $prodi->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Program studi berhasil diperbarui.',
            'data' => $prodi
        ]);
    }

    public function destroy(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        assert($user !== null);
        $id_kampus = $user->id_kampus;
        $prodi = Prodi::where('id', $id)->where('id_kampus', $id_kampus)->firstOrFail();
        $prodi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Program studi berhasil dihapus.'
        ]);
    }
}
