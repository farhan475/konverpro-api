<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranskripAsal extends Model
{
    protected $table = 'transkrip_asal';

    public $timestamps = false;

    protected $fillable = [
        'id_pendaftar',
        'nama_mk_asal',
        'sks_asal',
        'nilai_huruf_asal',
        'nilai_angka_asal',
    ];

    protected $casts = [
        'sks_asal' => 'integer',
        'nilai_angka_asal' => 'decimal:2',
    ];

    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(Pendaftar::class, 'id_pendaftar');
    }

    public function hasilKonversi(): HasMany
    {
        return $this->hasMany(HasilKonversi::class, 'id_transkrip_asal');
    }
}
