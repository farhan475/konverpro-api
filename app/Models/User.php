<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $table = 'users';

    protected $fillable = [
        'nama_lengkap',
        'email',
        'no_whatsapp',
        'password',
        'role',
        'avatar_path',
        'tanda_tangan_path',
        'status',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_login' => 'datetime',
        'role' => RoleEnum::class,
        'password' => 'hashed',
    ];

    /** @return HasMany<Prodi, $this> */
    public function prodiDipimpin(): HasMany
    {
        return $this->hasMany(Prodi::class, 'id_kaprodi');
    }

    /** @return HasMany<Pendaftar, $this> */
    public function pendaftarDibuat(): HasMany
    {
        return $this->hasMany(Pendaftar::class, 'created_by');
    }

    /** @return HasMany<InternalNotification, $this> */
    public function internalNotifications(): HasMany
    {
        return $this->hasMany(InternalNotification::class, 'id_user');
    }
}
