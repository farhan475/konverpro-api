<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\PengaturanGlobal;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    use ApiResponse;

    private const ALLOWED_KEYS = [
        'nama_institusi',
        'fuzzy_threshold_auto',
        'fuzzy_threshold_sumopod',
        'min_nilai_huruf_konversi',
        'max_konversi_sks_persen',
        'format_no_ba',
        'sumopod_api_key',
        'sumopod_model',
        'sumopod_base_url',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_from_name',
        'fonnte_api_key',
        'notif_email_aktif',
        'notif_wa_aktif',
    ];

    public function __construct(private AuditService $audit) {}

    public function index(): JsonResponse
    {
        $settings = collect(self::ALLOWED_KEYS)->mapWithKeys(function (string $key) {
            $value = PengaturanGlobal::get($key);
            // Mask nilai sensitif agar tidak bocor ke frontend
            if (PengaturanGlobal::isEncrypted($key)) {
                $value = $value ? '••••••••' : null;
            }
            return [$key => $value];
        });

        return $this->successResponse($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings'   => 'required|array',
            'settings.*' => 'nullable|string|max:500',
        ]);

        foreach ($request->settings as $key => $value) {
            if (!in_array($key, self::ALLOWED_KEYS)) continue;
            if ($value === '••••••••') continue;
            PengaturanGlobal::set($key, $value ?? '');
        }

        $this->audit->log('config.updated');

        return $this->successResponse(null, 'Konfigurasi berhasil disimpan.');
    }
}
