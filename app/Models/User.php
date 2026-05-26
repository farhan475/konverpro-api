<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    protected $table = 'users';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id_kampus',
        'nama_lengkap',
        'email',
        'no_whatsapp',
        'password_hash',
        'role',
        'avatar_path',
        'tanda_tangan_path',
        'status',
        'last_login',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'last_login' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function kampus(): BelongsTo
    {
        return $this->belongsTo(Kampus::class, 'id_kampus');
    }

    public function prodiDipimpin(): HasMany
    {
        return $this->hasMany(Prodi::class, 'id_kaprodi');
    }
}
