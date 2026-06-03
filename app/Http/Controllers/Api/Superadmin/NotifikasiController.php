<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotifikasiController extends Controller
{
    public function index()
    {
        $templates = DB::table('notifikasi_templates')->get();

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'subjek_email' => 'nullable|string|max:150',
            'konten_email' => 'nullable|string',
            'konten_wa' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        DB::table('notifikasi_templates')->where('id', $id)->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Template notifikasi berhasil diperbarui.'
        ]);
    }
}
