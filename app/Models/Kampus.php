<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kampus extends Model
{
    protected $table = 'kampus';

    protected $fillable = [
        'nama_kampus',
        'email_utama',
        'no_telp',
        'alamat_resmi',
        'rektor_pimpinan',
        'website',
        'logo_path',
        'paket_layanan',
        'is_official_partner',
        'tarif_internal_custom',
        'tarif_lead_custom',
        'saldo_aktif',
        'status_akun',
    ];

    protected $casts = [
        'is_official_partner' => 'boolean',
        'tarif_internal_custom' => 'decimal:2',
        'tarif_lead_custom' => 'decimal:2',
        'saldo_aktif' => 'decimal:2',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'id_kampus');
    }

    public function prodi(): HasMany
    {
        return $this->hasMany(Prodi::class, 'id_kampus');
    }

    public function pendaftar(): HasMany
    {
        return $this->hasMany(Pendaftar::class, 'id_kampus');
    }

    public function transaksiSaldo(): HasMany
    {
        return $this->hasMany(TransaksiSaldo::class, 'id_kampus');
    }
}
