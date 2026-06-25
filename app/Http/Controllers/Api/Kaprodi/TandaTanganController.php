<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TandaTanganController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    /**
     * Tampilkan tanda tangan kaprodi yang sedang login.
     */
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return $this->successResponse([
            'has_tanda_tangan' => ! is_null($user->tanda_tangan_path),
            'tanda_tangan_url' => $user->tanda_tangan_path
                ? url('api/files/tanda-tangan/'.$user->id)
                : null,
            'uploaded_at' => $user->updated_at?->toDateTimeString(),
        ]);
    }

    /**
     * Upload tanda tangan digital (image).
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $request->validate([
            'file' => 'required|image|mimes:png|max:1024',
        ]);

        $file = $request->file('file');
        if ($user->tanda_tangan_path) {
            Storage::disk('private')->delete($user->tanda_tangan_path);
        }

        $path = $file->storeAs(
            'tanda_tangan',
            'ttd_'.$user->id.'.'.$file->extension(),
            'private'
        );

        $user->update(['tanda_tangan_path' => $path]);

        $this->audit->log('upload_tanda_tangan', 'User', $user->id, 'Kaprodi mengupload tanda tangan digital');

        return $this->successResponse([
            'tanda_tangan_url' => url('api/files/tanda-tangan/'.$user->id),
        ], 'Tanda tangan berhasil diupload.');
    }

    /**
     * Hapus tanda tangan digital.
     */
    public function destroy(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->tanda_tangan_path) {
            return $this->errorResponse('Tidak ada tanda tangan untuk dihapus.', 404);
        }

        Storage::disk('private')->delete($user->tanda_tangan_path);

        $user->update(['tanda_tangan_path' => null]);

        $this->audit->log('hapus_tanda_tangan', 'User', $user->id, 'Kaprodi menghapus tanda tangan digital');

        return $this->successResponse(null, 'Tanda tangan berhasil dihapus.');
    }
}
