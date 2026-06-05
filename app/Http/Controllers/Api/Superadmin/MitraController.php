<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kampus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class MitraController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $mitra = Kampus::withCount(['pendaftar', 'prodi', 'users'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $mitra
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'nama_kampus' => 'required|string|max:150',
            'email_utama' => 'required|email|max:100|unique:kampus,email_utama',
            'no_telp' => 'nullable|string|max:20',
            'alamat_resmi' => 'nullable|string',
            'paket_layanan' => 'required|in:Enterprise,Premium',
            'status_akun' => 'required|in:active,pending,suspended',
            'admin_nama' => 'required|string|max:100',
            'admin_email' => 'required|email|max:100|unique:users,email',
            'admin_password' => 'required|string|min:6',
        ]);

        return DB::transaction(function () use ($validated) {
            $kampus = Kampus::create([
                'nama_kampus' => $validated['nama_kampus'],
                'email_utama' => $validated['email_utama'],
                'no_telp' => $validated['no_telp'],
                'alamat_resmi' => $validated['alamat_resmi'],
                'paket_layanan' => $validated['paket_layanan'],
                'status_akun' => $validated['status_akun'],
            ]);

            $user = User::create([
                'id_kampus' => $kampus->id,
                'nama_lengkap' => $validated['admin_nama'],
                'email' => $validated['admin_email'],
                'password_hash' => Hash::make($validated['admin_password']),
                'role' => 'admin_pt',
                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mitra dan Admin berhasil didaftarkan.',
                'data' => $kampus
            ], 201);
        });
    }

    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        $mitra = Kampus::with(['users' => function($q) {
            $q->where('role', 'admin_pt');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $mitra
        ]);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $kampus = Kampus::findOrFail($id);

        $validated = $request->validate([
            'nama_kampus' => 'required|string|max:150',
            'email_utama' => 'required|email|max:100|unique:kampus,email_utama,' . $id,
            'no_telp' => 'nullable|string|max:20',
            'alamat_resmi' => 'nullable|string',
            'paket_layanan' => 'required|in:Enterprise,Premium',
            'status_akun' => 'required|in:active,pending,suspended',
        ]);

        $kampus->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Informasi mitra berhasil diperbarui.'
        ]);
    }

    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $kampus = Kampus::findOrFail($id);
        $kampus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mitra berhasil dihapus dari sistem.'
        ]);
    }
}
