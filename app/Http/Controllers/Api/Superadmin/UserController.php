<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'nama_lengkap' => 'string|max:100',
            'email' => 'email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:8',
            'role' => 'in:superadmin,admin,akademik,kaprodi',
            'no_whatsapp' => 'nullable|string|max:20',
            'status' => 'in:active,inactive',
        ]);

        if (!empty($validated['password'])) {
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

        $user->delete();
        
        $this->audit->log('delete_user', 'User', $user->id, "Deleted user {$user->email}");

        return $this->successResponse(null, 'User deleted successfully.');
    }
}