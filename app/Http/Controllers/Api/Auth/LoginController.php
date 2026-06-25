<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;

class LoginController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        $password = $request->input('password');
        if (! $user || ! is_string($password) || ! Hash::check($password, $user->password)) {
            return $this->errorResponse('Email atau password salah.', 401);
        }

        if ($user->status !== 'active') {
            return $this->errorResponse('Akun Anda tidak aktif.', 403);
        }

        $user->tokens()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $token = $user->createToken(
            'auth_token',
            ['*'],
            now()->addHours(12)
        )->plainTextToken;

        $user->update(['last_login' => now()]);
        $this->audit->logAs(
            $user->id,
            'auth.login',
            'User',
            $user->id,
            'Login berhasil.',
            $request->ip()
        );

        return $this->successResponse([
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'email' => $user->email,
                'role' => $user->role->value,
                'avatar_path' => $user->avatar_path,
            ],
        ], 'Login berhasil.')->withCookie($this->tokenCookie($token));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->audit->logAs(
                $user->id,
                'auth.logout',
                'User',
                $user->id,
                'Logout berhasil.',
                $request->ip()
            );
            $user->currentAccessToken()->delete();
        }

        return $this->successResponse(null, 'Logout berhasil.')->withCookie(
            Cookie::create('konverpro_token')
                ->withValue('')
                ->withExpires(time() - 3600)
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite('lax')
        );
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
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

    private function tokenCookie(string $token): Cookie
    {
        return Cookie::create('konverpro_token')
            ->withValue($token)
            ->withExpires(now()->addHours(12))
            ->withPath('/')
            ->withSecure((bool) config('session.secure', false))
            ->withHttpOnly(true)
            ->withSameSite('lax');
    }
}
