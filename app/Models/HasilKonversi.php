<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilKonversi extends Model
{
    use HasUuids;

    protected $table = 'hasil_konversi';

    protected $fillable = [
        'id_pendaftar',
        'id_mk_tujuan',
        'id_transkrip_asal',
        'nilai_akhir_huruf',
        'sks_diakui',
        'metode_pemetaan',
        'match_score',
        'match_reason',
        'is_unmatched',
    ];

    protected $casts = [
        'is_unmatched' => 'boolean',
        'match_score' => 'decimal:2',
    ];

    /** @return BelongsTo<Pendaftar, $this> */
    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(Pendaftar::class, 'id_pendaftar');
    }

    /** @return BelongsTo<KurikulumMk, $this> */
    public function mkTujuan(): BelongsTo
    {
        return $this->belongsTo(KurikulumMk::class, 'id_mk_tujuan');
    }

    /** @return BelongsTo<TranskripAsal, $this> */
    public function transkripAsal(): BelongsTo
    {
        return $this->belongsTo(TranskripAsal::class, 'id_transkrip_asal');
    }
}
