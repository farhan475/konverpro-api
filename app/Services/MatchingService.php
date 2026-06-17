<?php

namespace App\Services;

use App\Enums\StatusPendaftarEnum;
use App\Models\HasilKonversi;
use App\Models\KamusSinonim;
use App\Models\KurikulumMk;
use App\Models\Pendaftar;
use App\Models\PengaturanGlobal;
use App\Models\TranskripAsal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchingService
{
    public function __construct(
        protected FuzzyMatcherService $fuzzyMatcher,
        protected SumopodService $sumopod
    ) {}

    public function processMatching(Pendaftar $pendaftar): void
    {
        $thresholdAuto = (float) PengaturanGlobal::get('fuzzy_threshold_auto', '80');
        $thresholdAi = (float) PengaturanGlobal::get('fuzzy_threshold_sumopod', '50');

        $transkrip = $pendaftar->transkripAsal;
        $kurikulum = KurikulumMk::where('id_prodi', $pendaftar->id_prodi)->get();

        if ($transkrip->isEmpty()) {
            throw new \RuntimeException('Data transkrip asal kosong.');
        }

        if ($kurikulum->isEmpty()) {
            throw new \RuntimeException('Data kurikulum prodi tujuan kosong.');
        }

        DB::beginTransaction();
        try {
            $pendaftar->update(['status' => StatusPendaftarEnum::AI_PROCESSING]);

            HasilKonversi::where('id_pendaftar', $pendaftar->id)->delete();

            foreach ($transkrip as $item) {
                $namaNormal = $this->normalizeWithKamus($item->nama_mk_asal);

                $bestMatch = null;
                $bestScore = 0.0;

                foreach ($kurikulum as $mkTujuan) {
                    $namaTujuanNormal = $this->normalizeWithKamus($mkTujuan->nama_mk);
                    $score = $this->fuzzyMatcher->getScore($namaNormal, $namaTujuanNormal);

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = $mkTujuan;
                    }
                }

                if ($bestScore >= $thresholdAuto && $bestMatch instanceof KurikulumMk) {
                    $this->saveMatch($pendaftar, $item, $bestMatch, $bestScore, 'Fuzzy');
                } elseif ($bestScore >= $thresholdAi && $bestMatch instanceof KurikulumMk) {
                    $aiMatch = $this->sumopod->getAiMatch($item->nama_mk_asal, $kurikulum->all());

                    if ($aiMatch && $aiMatch['id']) {
                        $mkTujuan = $kurikulum->firstWhere('id', $aiMatch['id']);
                        if ($mkTujuan instanceof KurikulumMk) {
                            $this->saveMatch($pendaftar, $item, $mkTujuan, $bestScore, 'Sumopod', (string) $aiMatch['reason']);
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

            $pendaftar->update(['status' => StatusPendaftarEnum::REVIEW_AKADEMIK]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $pendaftar->update(['status' => StatusPendaftarEnum::BARU]);
            Log::error('MatchingService failed', [
                'pendaftar_id' => $pendaftar->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function normalizeWithKamus(string $namaMk): string
    {
        $normalized = $this->normalizeText($namaMk);
        $kamus = KamusSinonim::where('is_active', true)->get();

        foreach ($kamus as $item) {
            $kataUtama = $this->normalizeText($item->kata_utama);
            $sinonim = $this->normalizeText($item->sinonim);

            if ($normalized === $sinonim || $normalized === $kataUtama) {
                return $kataUtama;
            }
        }

        return $normalized;
    }

    protected function normalizeText(string $text): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $text)));
    }

    protected function saveMatch(
        Pendaftar $pendaftar,
        TranskripAsal $asal,
        KurikulumMk $tujuan,
        float $score,
        string $metode,
        ?string $reason = null
    ): void {
        HasilKonversi::create([
            'id_pendaftar' => $pendaftar->id,
            'id_transkrip_asal' => $asal->id,
            'id_mk_tujuan' => $tujuan->id,
            'nilai_akhir_huruf' => $asal->nilai_huruf_asal,
            'sks_diakui' => min($asal->sks_asal, $tujuan->sks),
            'metode_pemetaan' => $metode,
            'match_score' => $score,
            'match_reason' => $reason ?? "Matched via {$metode}",
            'is_unmatched' => false,
        ]);
    }

    protected function saveUnmatched(Pendaftar $pendaftar, TranskripAsal $asal): void
    {
        HasilKonversi::create([
            'id_pendaftar' => $pendaftar->id,
            'id_transkrip_asal' => $asal->id,
            'id_mk_tujuan' => null,
            'sks_diakui' => 0,
            'is_unmatched' => true,
            'match_reason' => 'Tidak ditemukan padanan yang cukup.',
        ]);
    }
}
