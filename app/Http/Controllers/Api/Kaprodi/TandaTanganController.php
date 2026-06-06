<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TandaTanganController extends Controller
{
    use ApiResponse;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tanda_tangan' => 'required|image|max:2048',
        ]);

        /** @var User|null $user */
        $user = auth()->user();
        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }
        
        // Delete old one if exists
        if ($user->tanda_tangan_path) {
            Storage::disk('private')->delete($user->tanda_tangan_path);
        }

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('tanda_tangan');
        $path = $file->store('tanda_tangan', 'private');
        
        $user->update(['tanda_tangan_path' => $path]);
        
        AuditService::log('upload_tanda_tangan', 'User', $user->id, "Uploaded digital signature");

        return $this->successResponse(['path' => $path], 'Digital signature uploaded successfully.');
    }

    public function destroy(): JsonResponse
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if ($user->tanda_tangan_path) {
            Storage::disk('private')->delete($user->tanda_tangan_path);
            $user->update(['tanda_tangan_path' => null]);
        }
        return $this->successResponse(null, 'Digital signature deleted.');
    }
}
