<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pendaftar;

class MahasiswaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $id_kaprodi = $user->id;

        $mahasiswa = Pendaftar::whereHas('prodi', function ($query) use ($id_kaprodi) {
                $query->where('id_kaprodi', $id_kaprodi);
            })
            ->with(['prodi'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $mahasiswa
        ]);
    }
}
