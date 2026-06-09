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

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tanda_tangan' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->tanda_tangan_path) {
            Storage::disk('private')->delete($user->tanda_tangan_path);
        }

        $path = $request->file('tanda_tangan')->store('tanda_tangan', 'private');
        $user->update(['tanda_tangan_path' => $path]);

        $this->audit->log('tanda_tangan.upload', 'User', $user->id);

        return $this->successResponse(null, 'Tanda tangan berhasil disimpan.');
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->tanda_tangan_path) {
            Storage::disk('private')->delete($user->tanda_tangan_path);
            $user->update(['tanda_tangan_path' => null]);
        }

        return $this->successResponse(null, 'Tanda tangan berhasil dihapus.');
    }
}
