<?php

namespace App\Http\Controllers\Api\AdminPt;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;
        $users = User::where('id_kampus', $id_kampus)
            ->whereIn('role', ['staff', 'akademik', 'kaprodi'])
            ->withCount(['prodiDipimpin as total_prodi_dipegang'])
            ->orderBy('role')
            ->orderBy('nama_lengkap')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function store(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'no_whatsapp' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'role' => 'required|in:staff,akademik,kaprodi',
            'status' => 'required|in:active,inactive',
        ]);

        $user = User::create([
            'id_kampus' => $id_kampus,
            'nama_lengkap' => $validated['nama_lengkap'],
            'email' => $validated['email'],
            'no_whatsapp' => $validated['no_whatsapp'],
            'password_hash' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan.',
            'data' => $user
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $id_kampus = $request->user()->id_kampus;
        $user = User::where('id', $id)
            ->where('id_kampus', $id_kampus)
            ->whereIn('role', ['staff', 'akademik', 'kaprodi'])
            ->firstOrFail();

        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email,' . $id,
            'no_whatsapp' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:staff,akademik,kaprodi',
            'status' => 'required|in:active,inactive',
        ]);

        $updateData = [
            'nama_lengkap' => $validated['nama_lengkap'],
            'email' => $validated['email'],
            'no_whatsapp' => $validated['no_whatsapp'],
            'role' => $validated['role'],
            'status' => $validated['status'],
        ];

        if (!empty($validated['password'])) {
            $updateData['password_hash'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diperbarui.',
            'data' => $user
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $id_kampus = $request->user()->id_kampus;
        $user = User::where('id', $id)
            ->where('id_kampus', $id_kampus)
            ->whereIn('role', ['staff', 'akademik', 'kaprodi'])
            ->firstOrFail();

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus.'
        ]);
    }
}
