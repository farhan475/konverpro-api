<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiSaldo extends Model
{
    protected $table = 'transaksi_saldo';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id_kampus',
        'jenis_transaksi',
        'nominal',
        'bukti_transfer',
        'keterangan',
        'catatan_admin',
        'status',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
    ];

    public function kampus(): BelongsTo
    {
        return $this->belongsTo(Kampus::class, 'id_kampus');
    }
}
