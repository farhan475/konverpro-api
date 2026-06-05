<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class LoginController extends Controller
{
    public function index(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\View\View
    {
        if (Auth::check()) {
            $user = Auth::user();
            assert($user !== null);
            return redirect($this->webRouteForRole($user->role));
        }

        /** @var view-string $viewName */
        $viewName = 'auth.login';
        return view($viewName);
    }

    public function process(Request $request): \Illuminate\Http\RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && Hash::check($credentials['password'], $user->password_hash)) {
            Auth::login($user);
            $request->session()->regenerate();

            $user->update(['last_login' => now()]);

            return redirect()->intended($this->webRouteForRole($user->role));
        }

        return back()->withErrors([
            'email' => 'Email atau kata sandi salah.',
        ])->withInput($request->only('email'));
    }

    public function logout(Request $request): \Illuminate\Http\RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Logout berhasil.');
    }

    private function webRouteForRole(string $role): string
    {
        $routes = [
            'superadmin' => route('superadmin.dashboard'),
            'admin_pt'   => route('admin-pt.dashboard'),
            'staff'      => route('staff.dashboard'),
            'akademik'   => route('akademik.dashboard'),
            'kaprodi'    => route('kaprodi.dashboard'),
        ];

        return $routes[$role] ?? '/';
    }
}
