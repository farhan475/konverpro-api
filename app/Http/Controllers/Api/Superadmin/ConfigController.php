<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigController extends Controller
{
    public function index()
    {
        $settings = DB::table('pengaturan_global')->get()->pluck('setting_value', 'setting_key');

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $settings = $request->all();

        foreach ($settings as $key => $value) {
            DB::table('pengaturan_global')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'updated_at' => now()]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi global berhasil disimpan.'
        ]);
    }
}
