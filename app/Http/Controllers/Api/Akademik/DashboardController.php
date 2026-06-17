<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\HasilKonversi;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        // Compute real-time AI accuracy from HasilKonversi data
        $fuzzyAvg = (float) HasilKonversi::where('metode_pemetaan', 'Fuzzy')
            ->whereNotNull('match_score')
            ->avg('match_score');
        $fuzzyCount = HasilKonversi::where('metode_pemetaan', 'Fuzzy')
            ->whereNotNull('match_score')
            ->count();

        $aiAvg = (float) HasilKonversi::where('metode_pemetaan', 'Sumopod')
            ->whereNotNull('match_score')
            ->avg('match_score');
        $aiCount = HasilKonversi::where('metode_pemetaan', 'Sumopod')
            ->whereNotNull('match_score')
            ->count();

        return $this->successResponse([
            'stats' => [
                'antrean_baru' => Pendaftar::where('status', 'Baru')->count(),
                'ai_processing' => Pendaftar::where('status', 'AI Processing')->count(),
                'review_akademik' => Pendaftar::where('status', 'Review Akademik')->count(),
                'pending_kaprodi' => Pendaftar::where('status', 'Pending Kaprodi')->count(),
            ],
            'ai_performance' => [
                'fuzzy_accuracy' => round($fuzzyAvg, 1),
                'fuzzy_total' => $fuzzyCount,
                'ai_accuracy' => round($aiAvg, 1),
                'ai_total' => $aiCount,
                'fuzzy_threshold' => (float) PengaturanGlobal::get('fuzzy_threshold_auto', '80'),
                'ai_threshold' => (float) PengaturanGlobal::get('fuzzy_threshold_sumopod', '50'),
            ],
            'recent_queue' => Pendaftar::whereIn('status', ['Baru', 'Review Akademik'])->with('prodi')->latest()->limit(10)->get(),
        ]);
    }
}
