<?php

namespace App\Http\Controllers\Api\AdminPt;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prodi;
use App\Models\User;

class ProdiController extends Controller
{
    public function index(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;
        $prodi = Prodi::where('id_kampus', $id_kampus)
            ->with('kaprodi')
            ->orderBy('jenjang')
            ->orderBy('nama_prodi')
            ->get();

        return response()->json($prodi);
    }

    public function store(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;
        $validated = $request->validate([
            'id_kaprodi' => 'nullable|exists:users,id',
            'kode_prodi' => 'nullable|string|max:20',
            'nama_prodi' => 'required|string|max:100',
            'jenjang' => 'required|in:D3,D4,S1,S2',
            'biaya_pendaftaran' => 'numeric|min:0',
            'biaya_kuliah' => 'numeric|min:0',
        ]);

        $prodi = Prodi::create(array_merge($validated, ['id_kampus' => $id_kampus]));

        return response()->json($prodi, 201);
    }

    public function update(Request $request, $id)
    {
        $id_kampus = $request->user()->id_kampus;
        $prodi = Prodi::where('id', $id)->where('id_kampus', $id_kampus)->firstOrFail();

        $validated = $request->validate([
            'id_kaprodi' => 'nullable|exists:users,id',
            'kode_prodi' => 'nullable|string|max:20',
            'nama_prodi' => 'required|string|max:100',
            'jenjang' => 'required|in:D3,D4,S1,S2',
            'biaya_pendaftaran' => 'numeric|min:0',
            'biaya_kuliah' => 'numeric|min:0',
        ]);

        $prodi->update($validated);

        return response()->json($prodi);
    }

    public function destroy(Request $request, $id)
    {
        $id_kampus = $request->user()->id_kampus;
        $prodi = Prodi::where('id', $id)->where('id_kampus', $id_kampus)->firstOrFail();
        $prodi->delete();

        return response()->json(['message' => 'Program studi berhasil dihapus.']);
    }
}
