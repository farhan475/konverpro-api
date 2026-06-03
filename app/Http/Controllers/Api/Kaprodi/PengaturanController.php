<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prodi;
use App\Models\PengaturanProdi;

class PengaturanController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $my_prodi = Prodi::where('id_kaprodi', $user->id)
            ->with('pengaturan')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'nama_lengkap' => $user->nama_lengkap,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'my_prodi' => $my_prodi
            ]
        ]);
    }

    public function updateProdi(Request $request, $id_prodi)
    {
        $user = $request->user();
        
        $prodi = Prodi::where('id', $id_prodi)
            ->where('id_kaprodi', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'min_akreditasi_asal' => 'required|string',
            'max_usia_ijazah_tahun' => 'required|integer|min:1|max:20',
            'max_konversi_sks_persen' => 'required|integer|min:1|max:100',
            'min_nilai_huruf' => 'required|string|max:2',
            'min_ipk' => 'required|numeric|min:0|max:4',
            'metode_pengakuan' => 'required|string|in:direct,scale',
        ]);

        $pengaturan = PengaturanProdi::updateOrCreate(
            ['id_prodi' => $id_prodi],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan program studi berhasil diperbarui.',
            'data' => $pengaturan
        ]);
    }
}
