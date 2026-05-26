<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanGlobal extends Model
{
    protected $table = 'pengaturan_global';

    protected $primaryKey = 'setting_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];
}
