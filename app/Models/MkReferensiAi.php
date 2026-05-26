<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MkReferensiAi extends Model
{
    protected $table = 'mk_referensi_ai';

    protected $fillable = [
        'id_kurikulum_mk',
        'keyword',
        'keyword_normalized',
        'weight',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'weight' => 'integer',
        'is_active' => 'boolean',
    ];

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(KurikulumMk::class, 'id_kurikulum_mk');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
