<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\PengaturanGlobal;
use App\Services\AuditService;
use App\Services\InternalNotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConfigController extends Controller
{
    use ApiResponse;

    private const SECRET_MASK = '********';

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

    public function __construct(
        private AuditService $audit,
        private InternalNotificationService $notifications
    ) {}

    public function index(): JsonResponse
    {
        $settings = collect(self::ALLOWED_KEYS)->mapWithKeys(function (string $key) {
            $value = PengaturanGlobal::get($key);
            if (PengaturanGlobal::isEncrypted($key)) {
                $value = $value ? self::SECRET_MASK : null;
            }

            return [$key => $value];
        });

        return $this->successResponse($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string|max:500',
            'settings.fuzzy_threshold_auto' => 'nullable|numeric|min:0|max:100',
            'settings.fuzzy_threshold_sumopod' => 'nullable|numeric|min:0|max:100',
            'settings.max_konversi_sks_persen' => 'nullable|integer|min:0|max:100',
            'settings.smtp_port' => 'nullable|integer|min:1|max:65535',
            'settings.min_nilai_huruf_konversi' => 'nullable|string|in:A,B+,B,C+,C,D,E',
            'settings.sumopod_base_url' => 'nullable|url|max:500',
            'settings.notif_email_aktif' => 'nullable|in:true,false',
            'settings.notif_wa_aktif' => 'nullable|in:true,false',
        ]);

        /** @var array<string, string|null> $settings */
        $settings = $validated['settings'];
        $autoThreshold = isset($settings['fuzzy_threshold_auto'])
            ? (float) $settings['fuzzy_threshold_auto']
            : (float) PengaturanGlobal::get('fuzzy_threshold_auto', '80');
        $sumopodThreshold = isset($settings['fuzzy_threshold_sumopod'])
            ? (float) $settings['fuzzy_threshold_sumopod']
            : (float) PengaturanGlobal::get('fuzzy_threshold_sumopod', '50');

        if ($sumopodThreshold > $autoThreshold) {
            return $this->errorResponse(
                'Threshold Sumopod tidak boleh lebih tinggi dari threshold auto fuzzy.',
                422
            );
        }

        foreach ($settings as $key => $value) {
            if (! in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }

            if ($value === self::SECRET_MASK) {
                continue;
            }

            PengaturanGlobal::set($key, $value ?? '');
        }

        Cache::forget('fuzzy_threshold_auto');
        Cache::forget('fuzzy_threshold_sumopod');

        $this->audit->log('config.updated');
        $this->notifications->notifyRole(
            'superadmin',
            'config_updated',
            'Konfigurasi diperbarui',
            'Konfigurasi global KonverPro baru saja diperbarui.',
            '/superadmin/config',
            'PengaturanGlobal'
        );

        return $this->successResponse(null, 'Konfigurasi berhasil disimpan.');
    }
}
