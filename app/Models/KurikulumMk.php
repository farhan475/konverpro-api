<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KurikulumMk extends Model
{
    protected $table = 'kurikulum_mk';

    public $timestamps = false;

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
        'sks' => 'integer',
        'semester' => 'integer',
        'is_locked' => 'boolean',
    ];

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function hasilKonversi(): HasMany
    {
        return $this->hasMany(HasilKonversi::class, 'id_mk_tujuan');
    }

    public function referensiAi(): HasMany
    {
        return $this->hasMany(MkReferensiAi::class, 'id_kurikulum_mk');
    }
}
