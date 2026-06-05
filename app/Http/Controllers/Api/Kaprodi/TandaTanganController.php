<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TandaTanganController extends Controller
{
    /**
     * Mengambil profil tanda tangan Kaprodi saat ini
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        return response()->json([
            'nama_lengkap' => $user->nama_lengkap,
            'tanda_tangan_path' => $user->tanda_tangan_path ? asset('storage/' . $user->tanda_tangan_path) : null,
        ]);
    }

    /**
     * Mengunggah tanda tangan baru (berupa file gambar PNG/JPG)
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'tanda_tangan' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        /** @var User $user */
        $user = $request->user();

        // Hapus tanda tangan lama jika ada
        if ($user->tanda_tangan_path) {
            Storage::disk('public')->delete($user->tanda_tangan_path);
        }

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('tanda_tangan');
        $path = (string) $file->store('signatures', 'public');
        
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
    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->tanda_tangan_path) {
            Storage::disk('public')->delete($user->tanda_tangan_path);
            $user->tanda_tangan_path = null;
            $user->save();
        }

        return response()->json(['message' => 'Tanda tangan berhasil dihapus.']);
    }
}
