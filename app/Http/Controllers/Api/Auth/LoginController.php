<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class LoginController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'app_name' => config('app.name'),
            'user' => $request->user(),
            'csrf_token' => csrf_token()
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && Hash::check($credentials['password'], $user->password_hash)) {
            $token = $user->createToken('auth_token')->plainTextToken;
            $user->update(['last_login' => now()]);

            return response()->json([
                'user' => $user,
                'token' => $token,
            ], 200);
        }

        return response()->json([
            'message' => 'Email atau kata sandi salah.',
        ], 401);
    }

    public function me(Request $request)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Tidak ada sesi login aktif.',
                'csrf_token' => csrf_token()
            ], 401);
        }

        return response()->json([
            'user' => $request->user(),
            'csrf_token' => csrf_token()
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil.'], 200);
    }
}
