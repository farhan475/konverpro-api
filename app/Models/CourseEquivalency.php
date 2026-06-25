<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEquivalency extends Model
{
    use HasUuids;

    protected $fillable = [
        'asal_kampus',
        'asal_prodi',
        'nama_mk_asal',
        'normalized_key',
        'id_mk_tujuan',
        'sks_diakui',
        'alasan',
        'valid_from',
        'valid_until',
        'usage_count',
        'last_approved_by',
        'is_active',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<KurikulumMk, $this> */
    public function mkTujuan(): BelongsTo
    {
        return $this->belongsTo(KurikulumMk::class, 'id_mk_tujuan');
    }
}
