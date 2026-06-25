<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(User::orderBy('nama_lengkap')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'required|in:superadmin,admin,akademik,kaprodi',
            'no_whatsapp' => 'nullable|string|max:20',
        ]);

        $user = User::create($validated);

        $this->audit->log('create_user', 'User', $user->id, "Created user {$user->email}");

        return $this->successResponse($user, 'User created successfully.', 201);
    }

    public function show(User $user): JsonResponse
    {
        return $this->successResponse($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'nama_lengkap' => 'string|max:100',
            'email' => 'email|unique:users,email,'.$user->id,
            'password' => 'nullable|min:8',
            'role' => 'in:superadmin,admin,akademik,kaprodi',
            'no_whatsapp' => 'nullable|string|max:20',
            'status' => 'in:active,inactive',
        ]);

        if ($user->id === Auth::id() && ($validated['status'] ?? null) === 'inactive') {
            return $this->errorResponse('Akun yang sedang digunakan tidak dapat dinonaktifkan.', 422);
        }

        if ($this->wouldRemoveLastActiveSuperadmin($user, $validated)) {
            return $this->errorResponse('Minimal satu superadmin aktif harus tetap tersedia.', 422);
        }

        if (! empty($validated['password'])) {
            $validated['password'] = $validated['password']; // Model handles hashing
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        $this->audit->log('update_user', 'User', $user->id, "Updated user {$user->email}");

        return $this->successResponse($user, 'User updated successfully.');
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === Auth::id()) {
            return $this->errorResponse('Cannot delete yourself.', 400);
        }

        if ($user->role->value === 'superadmin' && $user->status === 'active'
            && User::where('role', 'superadmin')->where('status', 'active')->count() <= 1) {
            return $this->errorResponse('Minimal satu superadmin aktif harus tetap tersedia.', 422);
        }

        $user->delete();

        $this->audit->log('delete_user', 'User', $user->id, "Deleted user {$user->email}");

        return $this->successResponse(null, 'User deleted successfully.');
    }

    /** @param array<string, mixed> $changes */
    private function wouldRemoveLastActiveSuperadmin(User $user, array $changes): bool
    {
        if ($user->role->value !== 'superadmin' || $user->status !== 'active') {
            return false;
        }

        $newRole = $changes['role'] ?? $user->role->value;
        $newStatus = $changes['status'] ?? $user->status;
        if ($newRole === 'superadmin' && $newStatus === 'active') {
            return false;
        }

        return User::where('role', 'superadmin')->where('status', 'active')->count() <= 1;
    }
}
