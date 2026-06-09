<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(
            User::orderBy('nama_lengkap')->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:100',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|min:8',
            'role'         => 'required|in:superadmin,admin,akademik,kaprodi',
            'no_whatsapp'  => 'nullable|string|max:20',
            'status'       => 'nullable|in:active,inactive',
        ]);

        $user = User::create($validated);
        $this->audit->log('user.created', 'User', $user->id, "Created user {$user->email}");

        return $this->createdResponse($user, 'User berhasil dibuat.');
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'nama_lengkap' => 'sometimes|string|max:100',
            'email'        => 'sometimes|email|unique:users,email,' . $user->id,
            'password'     => 'nullable|min:8',
            'role'         => 'sometimes|in:superadmin,admin,akademik,kaprodi',
            'no_whatsapp'  => 'nullable|string|max:20',
            'status'       => 'sometimes|in:active,inactive',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);
        $this->audit->log('user.updated', 'User', $user->id, "Updated user {$user->email}");

        return $this->successResponse($user, 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->errorResponse('Tidak dapat menghapus akun sendiri.', 422);
        }

        $this->audit->log('user.deleted', 'User', $user->id, "Deleted user {$user->email}");
        $user->delete();

        return $this->successResponse(null, 'User berhasil dihapus.');
    }
}