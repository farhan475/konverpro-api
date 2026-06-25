<?php

namespace App\Services;

use App\Models\CourseEquivalency;
use App\Models\HasilKonversi;
use App\Models\Pendaftar;
use App\Models\TranskripAsal;

class CourseEquivalencyService
{
    public function normalizedKey(string $campus, ?string $program, string $course): string
    {
        return implode('|', array_map(
            fn (?string $value): string => strtolower(trim((string) preg_replace('/\s+/', ' ', $value ?? ''))),
            [$campus, $program, $course]
        ));
    }

    public function find(Pendaftar $pendaftar, TranskripAsal $transkrip): ?CourseEquivalency
    {
        $key = $this->normalizedKey(
            (string) $pendaftar->asal_kampus,
            $pendaftar->asal_prodi,
            $transkrip->nama_mk_asal
        );

        return CourseEquivalency::query()
            ->where('normalized_key', $key)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', today())
            ->where(function ($query): void {
                $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', today());
            })
            ->with('mkTujuan')
            ->latest('updated_at')
            ->first();
    }

    public function learnFromApproval(Pendaftar $pendaftar, string $approvedBy): void
    {
        $pendaftar->loadMissing('hasilKonversi.transkripAsal', 'hasilKonversi.mkTujuan');

        $pendaftar->hasilKonversi
            ->filter(fn (HasilKonversi $result): bool => ! $result->is_unmatched
                && $result->transkripAsal !== null
                && $result->mkTujuan !== null)
            ->each(function (HasilKonversi $result) use ($pendaftar, $approvedBy): void {
                $transkrip = $result->transkripAsal;
                $target = $result->mkTujuan;
                if (! $transkrip || ! $target) {
                    return;
                }

                $key = $this->normalizedKey(
                    (string) $pendaftar->asal_kampus,
                    $pendaftar->asal_prodi,
                    $transkrip->nama_mk_asal
                );

                $equivalency = CourseEquivalency::firstOrNew([
                    'normalized_key' => $key,
                    'id_mk_tujuan' => $target->id,
                ]);
                $equivalency->fill([
                    'asal_kampus' => (string) $pendaftar->asal_kampus,
                    'asal_prodi' => $pendaftar->asal_prodi,
                    'nama_mk_asal' => $transkrip->nama_mk_asal,
                    'sks_diakui' => (int) $result->sks_diakui,
                    'alasan' => $result->match_reason,
                    'valid_from' => today(),
                    'last_approved_by' => $approvedBy,
                    'is_active' => true,
                    'usage_count' => $equivalency->exists ? $equivalency->usage_count + 1 : 1,
                ])->save();
            });
    }
}
