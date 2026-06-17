<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    use ApiResponse;

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        $password = $request->input('password');
        if (!$user || !is_string($password) || !Hash::check($password, $user->password)) {
            return $this->errorResponse('Email atau password salah.', 401);
        }

        if ($user->status !== 'active') {
            return $this->errorResponse('Akun Anda tidak aktif.', 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->update(['last_login' => now()]);

        return $this->successResponse([
            'access_token' => $token,
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'email' => $user->email,
                'role' => $user->role->value,
                'avatar_path' => $user->avatar_path,
            ],
        ], 'Login berhasil.');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->currentAccessToken()->delete();
        }

        return $this->successResponse(null, 'Logout berhasil.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        return $this->successResponse([
            'id' => $user->id,
            'nama_lengkap' => $user->nama_lengkap,
            'email' => $user->email,
            'role' => $user->role->value,
            'avatar_path' => $user->avatar_path,
            'no_whatsapp' => $user->no_whatsapp,
            'status' => $user->status,
        ]);
    }
}