<?php

namespace App\Services;

use App\Models\HasilKonversi;
use App\Models\KamusSinonim;
use App\Models\KurikulumMk;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use App\Models\TranskripAsal;
use Illuminate\Support\Facades\Log;

class MatchingService
{
    public function __construct(
        protected FuzzyMatcherService $fuzzyMatcher,
        protected SumopodService $sumopod
    ) {}

    public function processMatching(Pendaftar $pendaftar): void
    {
        $transkrip = $pendaftar->transkripAsal;
        $kurikulum = KurikulumMk::where('id_prodi', $pendaftar->id_prodi)->get();
        
        $thresholdAuto = (float) PengaturanGlobal::get('fuzzy_threshold_auto', '80');
        $thresholdAi   = (float) PengaturanGlobal::get('fuzzy_threshold_sumopod', '50');

        foreach ($transkrip as $item) {
            $namaMkNormal = $this->normalizeWithKamus($item->nama_mk_asal);
            
            /** @var KurikulumMk|null $bestMatch */
            $bestMatch = null;
            $bestScore = 0.0;

            foreach ($kurikulum as $mkTujuan) {
                $score = $this->fuzzyMatcher->getScore($namaMkNormal, $mkTujuan->nama_mk);
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $mkTujuan;
                }
            }

            if ($bestScore >= $thresholdAuto && $bestMatch instanceof KurikulumMk) {
                $this->saveMatch($pendaftar, $item, $bestMatch, $bestScore, 'Fuzzy');
            } elseif ($bestScore >= $thresholdAi) {
                // Try Sumopod
                $aiMatch = $this->sumopod->getAiMatch($item->nama_mk_asal, $kurikulum->all());
                
                if ($aiMatch && isset($aiMatch['id']) && is_string($aiMatch['id']) && $aiMatch['id']) {
                    $mkTujuan = $kurikulum->firstWhere('id', $aiMatch['id']);
                    if ($mkTujuan instanceof KurikulumMk) {
                        $reason = isset($aiMatch['reason']) ? strval($aiMatch['reason']) : '';
                        $this->saveMatch($pendaftar, $item, $mkTujuan, $bestScore, 'Sumopod', $reason);
                    } else {
                        $this->saveUnmatched($pendaftar, $item);
                    }
                } else {
                    $this->saveUnmatched($pendaftar, $item);
                }
            } else {
                $this->saveUnmatched($pendaftar, $item);
            }
        }
    }

    protected function normalizeWithKamus(string $namaMk): string
    {
        $normalized = strtolower(trim($namaMk));
        
        $kamus = KamusSinonim::where('is_active', true)->get();
        
        foreach ($kamus as $k) {
            if (strtolower($k->sinonim) === $normalized) {
                return strtolower($k->kata_utama);
            }
        }

        return $normalized;
    }

    protected function saveMatch(Pendaftar $pendaftar, TranskripAsal $asal, KurikulumMk $tujuan, float $score, string $metode, ?string $reason = null): void
    {
        HasilKonversi::updateOrCreate(
            ['id_pendaftar' => $pendaftar->id, 'id_transkrip_asal' => $asal->id],
            [
                'id_mk_tujuan' => $tujuan->id,
                'nilai_akhir_huruf' => $asal->nilai_huruf_asal, // Default follow asal
                'sks_diakui' => min($asal->sks_asal, $tujuan->sks),
                'metode_pemetaan' => $metode,
                'match_score' => $score,
                'match_reason' => $reason ?? "Matched via {$metode}",
                'is_unmatched' => false
            ]
        );
    }

    protected function saveUnmatched(Pendaftar $pendaftar, TranskripAsal $asal): void
    {
        HasilKonversi::updateOrCreate(
            ['id_pendaftar' => $pendaftar->id, 'id_transkrip_asal' => $asal->id],
            [
                'id_mk_tujuan' => null,
                'is_unmatched' => true,
                'match_reason' => 'No confident match found'
            ]
        );
    }
}