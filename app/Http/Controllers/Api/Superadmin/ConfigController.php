<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\PengaturanGlobal;
use App\Traits\ApiResponse;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->successResponse(
            PengaturanGlobal::query()->pluck('setting_value', 'setting_key')
        );
    }

    public function update(Request $request): JsonResponse
    {
        $settings = $request->all();

        foreach ($settings as $key => $value) {
            PengaturanGlobal::updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value]
            );
        }
        
        AuditService::log('update_config', null, null, "Updated global settings");

        return $this->successResponse(null, 'Settings updated successfully.');
    }
}
