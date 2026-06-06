<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranskripAsal extends Model
{
    use HasUuids;

    protected $table = 'transkrip_asal';

    protected $fillable = [
        'id_pendaftar',
        'nama_mk_asal',
        'sks_asal',
        'nilai_huruf_asal',
        'nilai_angka_asal',
    ];

    /** @return BelongsTo<Pendaftar, $this> */
    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(Pendaftar::class, 'id_pendaftar');
    }

    /** @return HasMany<HasilKonversi, $this> */
    public function hasilKonversi(): HasMany
    {
        return $this->hasMany(HasilKonversi::class, 'id_transkrip_asal');
    }
}
