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
        'ai_config',
        'status_akun',
    ];

    protected $casts = [
        'is_official_partner' => 'boolean',
        'ai_config' => 'array',
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
}
