<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property float|null $avg_sks_diakui
 * @property int|null $total_sks_diakui
 */
class Prodi extends Model
{
    protected $table = 'prodi';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id_kampus',
        'id_kaprodi',
        'kode_prodi',
        'nama_prodi',
        'jenjang',
        'biaya_pendaftaran',
        'biaya_kuliah',
        'file_kurikulum_path',
    ];

    protected $casts = [
        'biaya_pendaftaran' => 'decimal:2',
        'biaya_kuliah' => 'decimal:2',
    ];

    public function kampus(): BelongsTo
    {
        return $this->belongsTo(Kampus::class, 'id_kampus');
    }

    public function kaprodi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_kaprodi');
    }

    public function pengaturan(): HasOne
    {
        return $this->hasOne(PengaturanProdi::class, 'id_prodi');
    }

    public function kurikulum(): HasMany
    {
        return $this->hasMany(KurikulumMk::class, 'id_prodi');
    }

    public function pendaftar(): HasMany
    {
        return $this->hasMany(Pendaftar::class, 'id_prodi');
    }
}
