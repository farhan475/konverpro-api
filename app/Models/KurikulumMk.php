<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KurikulumMk extends Model
{
    use HasUuids;

    protected $table = 'kurikulum_mk';

    protected $fillable = [
        'id_prodi',
        'kode_mk',
        'nama_mk',
        'deskripsi_singkat',
        'sks',
        'semester',
        'tipe_mk',
        'is_locked',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
    ];

    /** @return BelongsTo<Prodi, $this> */
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }
}
