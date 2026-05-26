<?php

namespace App\Http\Controllers\Api\AdminPt;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class KaprodiController extends Controller
{
    public function index(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;
        $kaprodi = User::where('id_kampus', $id_kampus)
            ->where('role', 'kaprodi')
            ->withCount('prodiDipimpin')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($kaprodi);
    }

    public function store(Request $request)
    {
        $id_kampus = $request->user()->id_kampus;
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'no_whatsapp' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'status' => 'required|in:active,inactive',
        ]);

        $kaprodi = User::create([
            'id_kampus' => $id_kampus,
            'nama_lengkap' => $validated['nama_lengkap'],
            'email' => $validated['email'],
            'no_whatsapp' => $validated['no_whatsapp'],
            'password_hash' => Hash::make($validated['password']),
            'role' => 'kaprodi',
            'status' => $validated['status'],
        ]);

        return response()->json($kaprodi, 201);
    }

    public function update(Request $request, $id)
    {
        $id_kampus = $request->user()->id_kampus;
        $kaprodi = User::where('id', $id)->where('id_kampus', $id_kampus)->where('role', 'kaprodi')->firstOrFail();

        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email,' . $id,
            'no_whatsapp' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'status' => 'required|in:active,inactive',
        ]);

        $updateData = [
            'nama_lengkap' => $validated['nama_lengkap'],
            'email' => $validated['email'],
            'no_whatsapp' => $validated['no_whatsapp'],
            'status' => $validated['status'],
        ];

        if (!empty($validated['password'])) {
            $updateData['password_hash'] = Hash::make($validated['password']);
        }

        $kaprodi->update($updateData);

        return response()->json($kaprodi);
    }

    public function destroy(Request $request, $id)
    {
        $id_kampus = $request->user()->id_kampus;
        $kaprodi = User::where('id', $id)->where('id_kampus', $id_kampus)->where('role', 'kaprodi')->firstOrFail();
        
        if ($kaprodi->prodiDipimpin()->count() > 0) {
            return response()->json(['message' => 'Tidak dapat menghapus Kaprodi yang masih memiliki Program Studi.'], 422);
        }

        $kaprodi->delete();

        return response()->json(['message' => 'Data Kaprodi berhasil dihapus.']);
    }
}
