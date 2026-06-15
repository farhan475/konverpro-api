<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prodi extends Model
{
    use HasUuids;

    protected $table = 'prodi';

    protected $fillable = [
        'id_kaprodi',
        'kode_prodi',
        'nama_prodi',
        'jenjang',
    ];

    /** @return BelongsTo<User, $this> */
    public function pengaturan(): HasOne
    {
        return $this->hasOne(PengaturanProdi::class, 'id_prodi');
    }

    public function kaprodi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_kaprodi');
    }

    /** @return HasMany<KurikulumMk, $this> */
    public function kurikulumMk(): HasMany
    {
        return $this->hasMany(KurikulumMk::class, 'id_prodi');
    }

    /** @return HasMany<Pendaftar, $this> */
    public function pendaftar(): HasMany
    {
        return $this->hasMany(Pendaftar::class, 'id_prodi');
    }
}
