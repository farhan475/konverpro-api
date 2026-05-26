<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilKonversi extends Model
{
    protected $table = 'hasil_konversi';

    public $timestamps = false;

    protected $fillable = [
        'id_pendaftar',
        'id_mk_tujuan',
        'id_transkrip_asal',
        'nilai_akhir_huruf',
        'sks_diakui',
        'metode_pemetaan',
        'match_score',
        'match_method',
        'match_reason',
    ];

    protected $casts = [
        'sks_diakui' => 'integer',
        'match_score' => 'decimal:2',
    ];

    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(Pendaftar::class, 'id_pendaftar');
    }

    public function mataKuliahTujuan(): BelongsTo
    {
        return $this->belongsTo(KurikulumMk::class, 'id_mk_tujuan');
    }

    public function transkripAsal(): BelongsTo
    {
        return $this->belongsTo(TranskripAsal::class, 'id_transkrip_asal');
    }
}
