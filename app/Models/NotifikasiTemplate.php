<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotifikasiTemplate extends Model
{
    protected $table = 'notifikasi_templates';

    public const CREATED_AT = null;

    protected $fillable = [
        'kode_event',
        'nama_event',
        'subjek_email',
        'konten_email',
        'konten_wa',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
