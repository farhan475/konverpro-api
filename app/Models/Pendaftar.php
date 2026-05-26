<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pendaftar extends Model
{
    protected $table = 'pendaftar';

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'id_kampus',
        'id_prodi',
        'nama_lengkap',
        'email',
        'no_whatsapp',
        'asal_kampus',
        'file_transkrip_path',
        'jalur_masuk',
        'status',
        'total_sks_diakui',
        'catatan_revisi',
        'hash_ba_digital',
    ];

    protected $casts = [
        'total_sks_diakui' => 'integer',
    ];

    public function kampus(): BelongsTo
    {
        return $this->belongsTo(Kampus::class, 'id_kampus');
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function transkripAsal(): HasMany
    {
        return $this->hasMany(TranskripAsal::class, 'id_pendaftar');
    }

    public function hasilKonversi(): HasMany
    {
        return $this->hasMany(HasilKonversi::class, 'id_pendaftar');
    }
}
