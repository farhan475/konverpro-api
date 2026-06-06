<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanGlobal extends Model
{
    protected $table = 'pengaturan_global';
    protected $primaryKey = 'setting_key';
    public $incrementing = false;
    protected $keyType = 'string';

    public const UPDATED_AT = 'updated_at';
    public const CREATED_AT = null;

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];
}
