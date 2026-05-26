<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class TandaTanganController extends Controller
{
    /**
     * Mengambil profil tanda tangan Kaprodi saat ini
     */
    public function show(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'nama_lengkap' => $user->nama_lengkap,
            'tanda_tangan_path' => $user->tanda_tangan_path ? asset('storage/' . $user->tanda_tangan_path) : null,
        ]);
    }

    /**
     * Mengunggah tanda tangan baru (berupa file gambar PNG/JPG)
     */
    public function upload(Request $request)
    {
        $request->validate([
            'tanda_tangan' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        $user = $request->user();

        // Hapus tanda tangan lama jika ada
        if ($user->tanda_tangan_path) {
            Storage::disk('public')->delete($user->tanda_tangan_path);
        }

        $path = $request->file('tanda_tangan')->store('signatures', 'public');
        
        $user->tanda_tangan_path = $path;
        $user->save();

        return response()->json([
            'message' => 'Tanda tangan berhasil diperbarui.',
            'path' => asset('storage/' . $path)
        ]);
    }

    /**
     * Menghapus tanda tangan
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->tanda_tangan_path) {
            Storage::disk('public')->delete($user->tanda_tangan_path);
            $user->tanda_tangan_path = null;
            $user->save();
        }

        return response()->json(['message' => 'Tanda tangan berhasil dihapus.']);
    }
}
